<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartRequest;
use App\Http\Requests\Admin\UpdatePartRequest;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Scopes\WorkshopScope;
use App\Services\Inventory\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(protected ProductImportService $importer) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');
        $brandId = $request->query('brand_id');

        $parts = Part::query()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('oem_part_number', 'like', "%{$q}%")
                        ->orWhere('barcode', 'like', "%{$q}%");
                });
            })
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->when($categoryId, fn ($qb) => $qb->where('category_id', $categoryId))
            ->when($brandId, fn ($qb) => $qb->where('brand_id', $brandId))
            ->with(['category:id,name', 'brand:id,name', 'unit:id,short_code'])
            ->withSum('inventoryItems as on_hand', 'quantity')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', [
            'title' => 'Products',
            'parts' => $parts,
            'q' => $q,
            'active' => $active,
            'categoryId' => $categoryId,
            'brandId' => $brandId,
            'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'title' => 'New product',
            'categories' => PartCategory::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_code']),
        ]);
    }

    public function store(StorePartRequest $request): RedirectResponse
    {
        $part = Part::create($request->validated());
        AuditLog::record('part.created', $part, $part->only(['name', 'sku', 'cost_price', 'sale_price']));

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function show(Part $product): View
    {
        $product->load(['category', 'brand', 'unit']);
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
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'short_code']),
        ]);
    }

    public function update(UpdatePartRequest $request, Part $product): RedirectResponse
    {
        $before = $product->only(['name', 'sku', 'cost_price', 'sale_price', 'is_active']);
        $product->update($request->validated());
        AuditLog::record('part.updated', $product, ['before' => $before, 'after' => $product->only(['name', 'sku', 'cost_price', 'sale_price', 'is_active'])]);

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
