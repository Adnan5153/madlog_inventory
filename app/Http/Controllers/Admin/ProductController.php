<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartRequest;
use App\Http\Requests\Admin\UpdatePartRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Scopes\WorkshopScope;
use App\Services\Inventory\ProductImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function __construct(protected ProductImportService $importer) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');
        $brand = trim((string) $request->query('brand', ''));
        $sort = (string) $request->query('sort', 'name_asc');

        [$sortColumn, $sortDirection] = $this->resolveSort($sort);

        $parts = $this->buildPartsQuery($request, $q, $active, $categoryId, $brand, $sortColumn, $sortDirection)
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'title' => 'Products',
            'parts' => $parts,
            'q' => $q,
            'active' => $active,
            'categoryId' => $categoryId,
            'brand' => $brand,
            'sort' => $sort,
            'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Live-search endpoint backing the products index typeahead. Returns
     * JSON shaped for the generic live-search JS:
     *   `{ rows: ["<tr>…</tr>", …], total: N, limit, truncated, word }`.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');
        $brand = trim((string) $request->query('brand', ''));
        $sort = (string) $request->query('sort', 'name_asc');

        [$sortColumn, $sortDirection] = $this->resolveSort($sort);

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.products._row-template',
            singular: 'product',
            builder: fn () => $this->buildPartsQuery($request, $q, $active, $categoryId, $brand, $sortColumn, $sortDirection),
        );
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$parts`.
     */
    protected function singularNoun(): string
    {
        return 'part';
    }

    /**
     * Build the filtered Parts query used by both index() and search().
     * Centralised so the two endpoints stay in lockstep.
     *
     * Match fields for `q`: every column rendered in the products list
     * table (name, sku, oem_part_number, barcode, description, brand,
     * location, cost_price, reorder_threshold, reorder_quantity,
     * is_active, plus the related category.name, unit.short_code, and
     * binLocation.code/description).
     */
    private function buildPartsQuery(
        Request $request,
        string $q,
        mixed $active,
        mixed $categoryId,
        string $brand,
        string $sortColumn,
        string $sortDirection,
    ): Builder {
        return Part::query()
            ->with(['category:id,name', 'unit:id,short_code', 'binLocation:id,code,zone,aisle,shelf'])
            ->withSum('inventoryItems as on_hand', 'quantity')
            ->when($q !== '', function (Builder $qb) use ($q) {
                $qb->where(function (Builder $w) use ($q) {
                    $like = '%'.$q.'%';

                    // Self columns (raw + numeric/boolean, cast to text).
                    $w->where('parts.name', 'like', $like)
                        ->orWhere('parts.sku', 'like', $like)
                        ->orWhere('parts.oem_part_number', 'like', $like)
                        ->orWhere('parts.barcode', 'like', $like)
                        ->orWhere('parts.description', 'like', $like)
                        ->orWhere('parts.brand', 'like', $like)
                        ->orWhere('parts.location', 'like', $like)
                        ->orWhereRaw('CAST(parts.cost_price AS CHAR) LIKE ?', [$like])
                        ->orWhereRaw('CAST(parts.reorder_threshold AS CHAR) LIKE ?', [$like])
                        ->orWhereRaw('CAST(parts.reorder_quantity AS CHAR) LIKE ?', [$like])
                        // Related model columns via subquery (Laravel
                        // doesn't support `whereHas` LIKE on a related
                        // column directly).
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like))
                        ->orWhereHas('unit', fn ($u) => $u->where(function ($w) use ($like) {
                            $w->where('short_code', 'like', $like)
                                ->orWhere('name', 'like', $like);
                        }))
                        ->orWhereHas('binLocation', fn ($b) => $b->where(function ($w) use ($like) {
                            $w->where('code', 'like', $like)
                                ->orWhere('description', 'like', $like);
                        }));
                });
            })
            ->when($active === 'yes', fn (Builder $qb) => $qb->where('parts.is_active', true))
            ->when($active === 'no', fn (Builder $qb) => $qb->where('parts.is_active', false))
            ->when($categoryId, fn (Builder $qb) => $qb->where('parts.category_id', $categoryId))
            ->when($brand !== '', fn (Builder $qb) => $qb->where('parts.brand', 'like', '%'.$brand.'%'))
            ->orderBy('parts.'.$sortColumn, $sortDirection)
            ->orderBy('parts.id'); // stable tie-breaker
    }

    /**
     * Map a sort token to [column, direction]. Falls back to name ascending
     * for unknown / unsafe values so a tampered query string can't be used
     * to inject ORDER BY clauses.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function resolveSort(string $sort): array
    {
        $allowed = [
            'name_asc' => ['name', 'asc'],
            'name_desc' => ['name', 'desc'],
            'recent' => ['created_at', 'desc'],
            'oldest' => ['created_at', 'asc'],
            'cost_asc' => ['cost_price', 'asc'],
            'cost_desc' => ['cost_price', 'desc'],
            'reorder_asc' => ['reorder_threshold', 'asc'],
            'reorder_desc' => ['reorder_threshold', 'desc'],
        ];

        return $allowed[$sort] ?? ['name', 'asc'];
    }

    /**
     * Active bin locations for the Storage dropdown. When `$workshopId`
     * is given, the global scope is bypassed so the dropdown always
     * shows the product's own workshop's bins (needed when a global
     * admin edits another tenant's record).
     */
    private function binLocationsForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return BinLocation::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'zone', 'aisle', 'shelf']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return BinLocation::query()
                ->where('is_active', true)
                ->where('workshop_id', $workshopId)
                ->orderBy('code')
                ->get(['id', 'code', 'zone', 'aisle', 'shelf']);
        });
    }

    private function suppliersForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return Supplier::query()
                ->where('is_active', true)
                ->where('workshop_id', $workshopId)
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'title' => 'New product',
            'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_code']),
            'workshops' => $this->workshopsForForm(),
            'binLocations' => $this->binLocationsForForm(),
            'suppliers' => $this->suppliersForForm(),
        ]);
    }

    public function store(StorePartRequest $request): RedirectResponse
    {
        $part = Part::create($request->validated());
        AuditLog::record('part.created', $part, $part->only(['name', 'sku', 'cost_price', 'workshop_id']));

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function show(Part $product): View
    {
        $product->load(['category', 'unit', 'binLocation', 'supplier']);
        $product->loadSum('inventoryItems as on_hand', 'quantity');

        $movements = WorkshopScope::disabled(function () use ($product) {
            return StockMovement::query()
                ->where('workshop_id', $product->workshop_id)
                ->where('part_id', $product->id)
                ->with(['bin:id,code', 'user:id,name'])
                ->latest('created_at')
                ->limit(30)
                ->get();
        });

        return view('admin.products.show', [
            'title' => $product->name,
            'product' => $product,
            'movements' => $movements,
        ]);
    }

    public function edit(Part $product): View
    {
        return view('admin.products.edit', [
            'title' => 'Edit product',
            'product' => $product,
            'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_code']),
            'workshops' => $this->workshopsForForm(),
            'binLocations' => $this->binLocationsForForm($product->workshop_id),
            'suppliers' => $this->suppliersForForm($product->workshop_id),
        ]);
    }

    public function update(UpdatePartRequest $request, Part $product): RedirectResponse
    {
        $before = $product->only(['name', 'sku', 'cost_price', 'is_active']);
        $product->update($request->validated());
        AuditLog::record('part.updated', $product, ['before' => $before, 'after' => $product->only(['name', 'sku', 'cost_price', 'is_active'])]);

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Part $product): RedirectResponse
    {
        if ($product->inventoryItems()->exists() || $product->stockMovements()->exists()) {
            return back()->withErrors(['product' => 'Cannot delete a product with inventory or stock movement history. Archive it instead.']);
        }

        AuditLog::record('part.deleted', $product, $product->only(['name', 'sku']));
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product deleted.');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:10240'],
        ]);

        $workshopId = $request->user()->workshop_id;
        if (! $workshopId) {
            return back()->withErrors(['file' => 'Your account is not bound to a workshop; CSV import is not available.']);
        }

        $result = $this->importer->importCsv($request->file('file'), $request->user(), $workshopId);

        $message = sprintf(
            'Imported %d, updated %d, skipped %d.',
            $result['created'],
            $result['updated'],
            $result['skipped'],
        );
        if (! empty($result['errors'])) {
            $message .= ' Errors: '.implode(' | ', array_slice($result['errors'], 0, 5));

            return back()->withErrors(['import' => $message]);
        }

        return back()->with('status', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $workshopId = $request->user()->workshop_id ?? 0;
        $csv = $this->importer->exportCsv($workshopId);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, 'products-'.date('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
