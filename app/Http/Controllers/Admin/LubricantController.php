<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LubricantApplication;
use App\Enums\LubricantPackageType;
use App\Enums\LubricantStatus;
use App\Enums\LubricantType;
use App\Enums\LubricantViscosity;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLubricantRequest;
use App\Http\Requests\Admin\UpdateLubricantRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\Supplier;
use App\Scopes\WorkshopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LubricantController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $lubricantType = $request->query('lubricant_type');
        $viscosityGrade = $request->query('viscosity_grade');
        $applicationType = $request->query('application_type');
        $brand = trim((string) $request->query('brand', ''));
        $supplierId = $request->query('supplier_id');
        $status = $request->query('status');
        $stockStatus = $request->query('stock_status');

        $lubricants = $this->buildLubricantsQuery(
            $request, $q, $lubricantType, $viscosityGrade, $applicationType, $brand, $supplierId, $status, $stockStatus,
        )->paginate(20)->withQueryString();

        return view('admin.lubricants.index', [
            'title' => 'Lubricants',
            'lubricants' => $lubricants,
            'q' => $q,
            'lubricantType' => $lubricantType,
            'viscosityGrade' => $viscosityGrade,
            'applicationType' => $applicationType,
            'brand' => $brand,
            'supplierId' => $supplierId,
            'status' => $status,
            'stockStatus' => $stockStatus,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'lubricantTypes' => LubricantType::cases(),
            'viscosities' => LubricantViscosity::cases(),
            'applications' => LubricantApplication::cases(),
            'statuses' => LubricantStatus::cases(),
        ]);
    }

    /**
     * Live-search JSON endpoint backing the lubricants index typeahead.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $lubricantType = $request->query('lubricant_type');
        $viscosityGrade = $request->query('viscosity_grade');
        $applicationType = $request->query('application_type');
        $brand = trim((string) $request->query('brand', ''));
        $supplierId = $request->query('supplier_id');
        $status = $request->query('status');
        $stockStatus = $request->query('stock_status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.lubricants._row-template',
            singular: 'lubricant',
            builder: fn () => $this->buildLubricantsQuery(
                $request, $q, $lubricantType, $viscosityGrade, $applicationType, $brand, $supplierId, $status, $stockStatus,
            ),
        );
    }

    /**
     * Row template loops over `$lubricants`.
     */
    protected function singularNoun(): string
    {
        return 'lubricant';
    }

    /**
     * Shared filtered query used by both index() and search().
     */
    private function buildLubricantsQuery(
        Request $request,
        string $q,
        mixed $lubricantType,
        mixed $viscosityGrade,
        mixed $applicationType,
        string $brand,
        mixed $supplierId,
        mixed $status,
        mixed $stockStatus,
    ): Builder {
        return Lubricant::query()
            ->with(['supplier:id,name', 'binLocation:id,code,zone,aisle,shelf'])
            ->withSum('lubricantInventoryItems as on_hand', 'quantity')
            ->when($q !== '', function (Builder $qb) use ($q) {
                $qb->where(function (Builder $w) use ($q) {
                    $like = '%'.$q.'%';
                    $w->where('lubricants.name', 'like', $like)
                        ->orWhere('lubricants.lubricant_code', 'like', $like)
                        ->orWhere('lubricants.sku', 'like', $like)
                        ->orWhere('lubricants.barcode', 'like', $like)
                        ->orWhere('lubricants.brand', 'like', $like)
                        ->orWhere('lubricants.manufacturer', 'like', $like)
                        ->orWhere('lubricants.manufacturer_part_number', 'like', $like)
                        ->orWhere('lubricants.notes', 'like', $like)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like))
                        ->orWhereHas('binLocation', fn ($b) => $b->where('code', 'like', $like));
                });
            })
            ->when($lubricantType, fn (Builder $qb) => $qb->where('lubricants.lubricant_type', $lubricantType))
            ->when($viscosityGrade, fn (Builder $qb) => $qb->where('lubricants.viscosity_grade', $viscosityGrade))
            ->when($applicationType, fn (Builder $qb) => $qb->where('lubricants.application_type', $applicationType))
            ->when($brand !== '', fn (Builder $qb) => $qb->where('lubricants.brand', 'like', '%'.$brand.'%'))
            ->when($supplierId, fn (Builder $qb) => $qb->where('lubricants.supplier_id', $supplierId))
            ->when($status, fn (Builder $qb) => $qb->where('lubricants.status', $status))
            ->when($stockStatus === 'in_stock', fn (Builder $qb) => $qb->inStock())
            ->when($stockStatus === 'low_stock', fn (Builder $qb) => $qb->lowStock())
            ->when($stockStatus === 'out_of_stock', fn (Builder $qb) => $qb->outOfStock())
            ->orderBy('lubricants.name')
            ->orderBy('lubricants.id');
    }

    /**
     * Active bin locations for the Storage dropdown. When `$workshopId`
     * is given, the global scope is bypassed so the dropdown always
     * shows the lubricant's own workshop's bins.
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

    public function create(Request $request): View
    {
        // For a global admin the workshop picker drives the bin/supplier
        // dropdowns — when a workshop is picked via `?workshop_id=`, only
        // that workshop's bins and suppliers are listed so they match the
        // `exists` validation on the FormRequest. Workshop-scoped admins
        // never see the picker and get their own workshop's rows via the
        // global `WorkshopScope`.
        $pickedWorkshopId = $this->selectedWorkshopId($request)
            ?? auth()->user()?->workshop_id;

        return view('admin.lubricants.create', [
            'title' => 'New lubricant',
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $pickedWorkshopId,
            'binLocations' => $this->binLocationsForForm($pickedWorkshopId),
            'suppliers' => $this->suppliersForForm($pickedWorkshopId),
            'lubricantTypes' => LubricantType::cases(),
            'viscosities' => LubricantViscosity::cases(),
            'applications' => LubricantApplication::cases(),
            'packageTypes' => LubricantPackageType::cases(),
            'statuses' => LubricantStatus::cases(),
        ]);
    }

    public function store(StoreLubricantRequest $request): RedirectResponse
    {
        $lubricant = Lubricant::create($request->validated());
        AuditLog::record('lubricant.created', $lubricant, $lubricant->only(['lubricant_code', 'sku', 'name', 'workshop_id']));

        return redirect()->route('admin.lubricants.index')->with('status', 'Lubricant created.');
    }

    public function show(Lubricant $lubricant): View
    {
        $lubricant->load(['supplier', 'binLocation']);
        $lubricant->loadSum('lubricantInventoryItems as on_hand', 'quantity');

        $movements = $lubricant->lubricantStockMovements()
            ->with(['bin:id,code', 'user:id,name'])
            ->latest('created_at')
            ->limit(30)
            ->get();

        return view('admin.lubricants.show', [
            'title' => $lubricant->name,
            'lubricant' => $lubricant,
            'movements' => $movements,
        ]);
    }

    public function edit(Lubricant $lubricant): View
    {
        return view('admin.lubricants.edit', [
            'title' => 'Edit lubricant',
            'lubricant' => $lubricant,
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $lubricant->workshop_id,
            'binLocations' => $this->binLocationsForForm($lubricant->workshop_id),
            'suppliers' => $this->suppliersForForm($lubricant->workshop_id),
            'lubricantTypes' => LubricantType::cases(),
            'viscosities' => LubricantViscosity::cases(),
            'applications' => LubricantApplication::cases(),
            'packageTypes' => LubricantPackageType::cases(),
            'statuses' => LubricantStatus::cases(),
        ]);
    }

    public function update(UpdateLubricantRequest $request, Lubricant $lubricant): RedirectResponse
    {
        $before = $lubricant->only(['name', 'sku', 'cost_price', 'is_active', 'status']);
        $lubricant->update($request->validated());
        AuditLog::record('lubricant.updated', $lubricant, [
            'before' => $before,
            'after' => $lubricant->only(['name', 'sku', 'cost_price', 'is_active', 'status']),
        ]);

        return redirect()->route('admin.lubricants.index')->with('status', 'Lubricant updated.');
    }

    public function destroy(Lubricant $lubricant): RedirectResponse
    {
        if ($lubricant->lubricantInventoryItems()->exists() || $lubricant->lubricantStockMovements()->exists()) {
            return back()->withErrors([
                'lubricant' => 'Cannot delete a lubricant with inventory or stock movement history. Archive it instead.',
            ]);
        }

        AuditLog::record('lubricant.deleted', $lubricant, $lubricant->only(['lubricant_code', 'name']));
        $lubricant->delete();

        return redirect()->route('admin.lubricants.index')->with('status', 'Lubricant deleted.');
    }
}
