<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BatteryChemistry;
use App\Enums\BatteryStatus;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatteryRequest;
use App\Http\Requests\Admin\UpdateBatteryRequest;
use App\Models\AuditLog;
use App\Models\Battery;
use App\Models\BinLocation;
use App\Models\Supplier;
use App\Scopes\WorkshopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatteryController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $batteryType = $request->query('battery_type');
        $brand = trim((string) $request->query('brand', ''));
        $supplierId = $request->query('supplier_id');
        $status = $request->query('status');
        $stockStatus = $request->query('stock_status');

        $batteries = $this->buildBatteriesQuery(
            $request, $q, $batteryType, $brand, $supplierId, $status, $stockStatus,
        )->paginate(20)->withQueryString();

        return view('admin.batteries.index', [
            'title' => 'Batteries',
            'batteries' => $batteries,
            'q' => $q,
            'batteryType' => $batteryType,
            'brand' => $brand,
            'supplierId' => $supplierId,
            'status' => $status,
            'stockStatus' => $stockStatus,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'chemistries' => BatteryChemistry::cases(),
            'statuses' => BatteryStatus::cases(),
        ]);
    }

    /**
     * Live-search JSON endpoint backing the batteries index typeahead.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $batteryType = $request->query('battery_type');
        $brand = trim((string) $request->query('brand', ''));
        $supplierId = $request->query('supplier_id');
        $status = $request->query('status');
        $stockStatus = $request->query('stock_status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.batteries._row-template',
            singular: 'battery',
            builder: fn () => $this->buildBatteriesQuery(
                $request, $q, $batteryType, $brand, $supplierId, $status, $stockStatus,
            ),
        );
    }

    /**
     * Row template loops over `$batteries`.
     */
    protected function singularNoun(): string
    {
        return 'battery';
    }

    /**
     * Shared filtered query used by both index() and search().
     */
    private function buildBatteriesQuery(
        Request $request,
        string $q,
        mixed $batteryType,
        string $brand,
        mixed $supplierId,
        mixed $status,
        mixed $stockStatus,
    ): Builder {
        return Battery::query()
            ->with(['supplier:id,name', 'binLocation:id,code,zone,aisle,shelf'])
            ->withSum('batteryInventoryItems as on_hand', 'quantity')
            ->when($q !== '', function (Builder $qb) use ($q) {
                $qb->where(function (Builder $w) use ($q) {
                    $like = '%'.$q.'%';
                    $w->where('batteries.name', 'like', $like)
                        ->orWhere('batteries.battery_code', 'like', $like)
                        ->orWhere('batteries.sku', 'like', $like)
                        ->orWhere('batteries.barcode', 'like', $like)
                        ->orWhere('batteries.brand', 'like', $like)
                        ->orWhere('batteries.manufacturer_part_number', 'like', $like)
                        ->orWhere('batteries.notes', 'like', $like)
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $like))
                        ->orWhereHas('binLocation', fn ($b) => $b->where('code', 'like', $like));
                });
            })
            ->when($batteryType, fn (Builder $qb) => $qb->where('batteries.battery_type', $batteryType))
            ->when($brand !== '', fn (Builder $qb) => $qb->where('batteries.brand', 'like', '%'.$brand.'%'))
            ->when($supplierId, fn (Builder $qb) => $qb->where('batteries.supplier_id', $supplierId))
            ->when($status, fn (Builder $qb) => $qb->where('batteries.status', $status))
            ->when($stockStatus === 'in_stock', fn (Builder $qb) => $qb->inStock())
            ->when($stockStatus === 'low_stock', fn (Builder $qb) => $qb->lowStock())
            ->when($stockStatus === 'out_of_stock', fn (Builder $qb) => $qb->outOfStock())
            ->orderBy('batteries.name')
            ->orderBy('batteries.id');
    }

    /**
     * Active bin locations for the Storage dropdown. When `$workshopId`
     * is given, the global scope is bypassed so the dropdown always
     * shows the battery's own workshop's bins.
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

        return view('admin.batteries.create', [
            'title' => 'New battery',
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $pickedWorkshopId,
            'binLocations' => $this->binLocationsForForm($pickedWorkshopId),
            'suppliers' => $this->suppliersForForm($pickedWorkshopId),
            'chemistries' => BatteryChemistry::cases(),
            'statuses' => BatteryStatus::cases(),
        ]);
    }

    public function store(StoreBatteryRequest $request): RedirectResponse
    {
        $battery = Battery::create($request->validated());
        AuditLog::record('battery.created', $battery, $battery->only(['battery_code', 'sku', 'name', 'workshop_id']));

        return redirect()->route('admin.batteries.index')->with('status', 'Battery created.');
    }

    public function show(Battery $battery): View
    {
        $battery->load(['supplier', 'binLocation']);
        $battery->loadSum('batteryInventoryItems as on_hand', 'quantity');

        $movements = $battery->batteryStockMovements()
            ->with(['bin:id,code', 'user:id,name'])
            ->latest('created_at')
            ->limit(30)
            ->get();

        return view('admin.batteries.show', [
            'title' => $battery->name,
            'battery' => $battery,
            'movements' => $movements,
        ]);
    }

    public function edit(Battery $battery): View
    {
        return view('admin.batteries.edit', [
            'title' => 'Edit battery',
            'battery' => $battery,
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $battery->workshop_id,
            'binLocations' => $this->binLocationsForForm($battery->workshop_id),
            'suppliers' => $this->suppliersForForm($battery->workshop_id),
            'chemistries' => BatteryChemistry::cases(),
            'statuses' => BatteryStatus::cases(),
        ]);
    }

    public function update(UpdateBatteryRequest $request, Battery $battery): RedirectResponse
    {
        $before = $battery->only(['name', 'sku', 'cost_price', 'is_active', 'status']);
        $battery->update($request->validated());
        AuditLog::record('battery.updated', $battery, [
            'before' => $before,
            'after' => $battery->only(['name', 'sku', 'cost_price', 'is_active', 'status']),
        ]);

        return redirect()->route('admin.batteries.index')->with('status', 'Battery updated.');
    }

    public function destroy(Battery $battery): RedirectResponse
    {
        if ($battery->batteryInventoryItems()->exists() || $battery->batteryStockMovements()->exists()) {
            return back()->withErrors([
                'battery' => 'Cannot delete a battery with inventory or stock movement history. Archive it instead.',
            ]);
        }

        AuditLog::record('battery.deleted', $battery, $battery->only(['battery_code', 'name']));
        $battery->delete();

        return redirect()->route('admin.batteries.index')->with('status', 'Battery deleted.');
    }
}
