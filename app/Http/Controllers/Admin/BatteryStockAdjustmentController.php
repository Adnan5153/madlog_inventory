<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BatteryStockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBatteryStockAdjustmentRequest;
use App\Models\AuditLog;
use App\Models\Battery;
use App\Models\BatteryStockAdjustment;
use App\Models\BatteryStockMovement;
use App\Models\BinLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BatteryStockAdjustmentController extends Controller
{
    use HasLiveSearch;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $adjustments = $this->buildAdjustmentsQuery($q, $status)
            ->paginate(20)
            ->withQueryString();

        return view('admin.battery-stock-adjustments.index', [
            'title' => 'Battery stock adjustments',
            'adjustments' => $adjustments,
            'q' => $q,
            'status' => $status,
            'statuses' => BatteryStockAdjustmentStatus::cases(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.battery-stock-adjustments._row-template',
            singular: 'battery-adjustment',
            builder: fn () => $this->buildAdjustmentsQuery($q, $status),
        );
    }

    protected function singularNoun(): string
    {
        return 'battery-adjustment';
    }

    private function buildAdjustmentsQuery(string $q, mixed $status)
    {
        return BatteryStockAdjustment::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with(['requester:id,name', 'approver:id,name'])
            ->withCount('items')
            ->latest('created_at');
    }

    public function create(Request $request): View
    {
        return view('admin.battery-stock-adjustments.create', [
            'title' => 'New battery stock adjustment',
            'batteries' => Battery::query()->orderBy('name')->get(['id', 'name', 'battery_code']),
            'binLocations' => BinLocation::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'zone']),
        ]);
    }

    public function store(StoreBatteryStockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $adjustment = DB::transaction(function () use ($data, $request) {
            $adj = BatteryStockAdjustment::create([
                'workshop_id' => $data['workshop_id'],
                'reference' => $data['reference'],
                'status' => BatteryStockAdjustmentStatus::Pending,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'requested_by' => $request->user()->getKey(),
            ]);

            $adj->items()->createMany($data['items']);

            return $adj;
        });

        AuditLog::record('battery-stock-adjustment.created', $adjustment, [
            'reference' => $adjustment->reference,
            'workshop_id' => $adjustment->workshop_id,
        ]);

        return redirect()->route('admin.battery-stock-adjustments.index')->with('status', 'Battery stock adjustment saved.');
    }

    public function show(BatteryStockAdjustment $batteryStockAdjustment): View
    {
        $batteryStockAdjustment->load([
            'requester', 'approver',
            'items.battery:id,name,battery_code',
            'items.bin:id,code',
        ]);

        return view('admin.battery-stock-adjustments.show', [
            'title' => $batteryStockAdjustment->reference,
            'adjustment' => $batteryStockAdjustment,
        ]);
    }

    public function approve(Request $request, BatteryStockAdjustment $batteryStockAdjustment): RedirectResponse
    {
        if ($batteryStockAdjustment->status !== BatteryStockAdjustmentStatus::Pending) {
            return back()->withErrors(['adjustment' => 'Only pending adjustments can be approved.']);
        }

        DB::transaction(function () use ($batteryStockAdjustment, $request) {
            foreach ($batteryStockAdjustment->items as $item) {
                BatteryStockMovement::create([
                    'workshop_id' => $batteryStockAdjustment->workshop_id,
                    'battery_id' => $item->battery_id,
                    'bin_id' => $item->bin_id,
                    'user_id' => $request->user()->getKey(),
                    'battery_inventory_item_id' => $item->battery_inventory_item_id,
                    'type' => StockMovementType::ManualAdjustment,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => BatteryStockAdjustment::class,
                    'reference_id' => $batteryStockAdjustment->id,
                    'reason' => $item->reason ?? $batteryStockAdjustment->reason,
                    'occurred_at' => now(),
                ]);

                if ($item->battery_inventory_item_id) {
                    $item->batteryInventoryItem?->increment('quantity', (float) $item->quantity);
                }
            }

            $batteryStockAdjustment->update([
                'status' => BatteryStockAdjustmentStatus::Approved,
                'approved_by' => $request->user()->getKey(),
                'approved_at' => now(),
            ]);
        });

        AuditLog::record('battery-stock-adjustment.approved', $batteryStockAdjustment, [
            'reference' => $batteryStockAdjustment->reference,
        ]);

        return back()->with('status', "Adjustment {$batteryStockAdjustment->reference} approved.");
    }

    public function reject(Request $request, BatteryStockAdjustment $batteryStockAdjustment): RedirectResponse
    {
        if ($batteryStockAdjustment->status !== BatteryStockAdjustmentStatus::Pending) {
            return back()->withErrors(['adjustment' => 'Only pending adjustments can be rejected.']);
        }

        $batteryStockAdjustment->update([
            'status' => BatteryStockAdjustmentStatus::Rejected,
            'approved_by' => $request->user()->getKey(),
            'approved_at' => now(),
        ]);

        AuditLog::record('battery-stock-adjustment.rejected', $batteryStockAdjustment, [
            'reference' => $batteryStockAdjustment->reference,
        ]);

        return back()->with('status', "Adjustment {$batteryStockAdjustment->reference} rejected.");
    }
}
