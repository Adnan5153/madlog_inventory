<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LubricantStockAdjustmentStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLubricantStockAdjustmentRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Lubricant;
use App\Models\LubricantStockAdjustment;
use App\Models\LubricantStockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LubricantStockAdjustmentController extends Controller
{
    use HasLiveSearch;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $adjustments = $this->buildAdjustmentsQuery($q, $status)
            ->paginate(20)
            ->withQueryString();

        return view('admin.lubricant-stock-adjustments.index', [
            'title' => 'Lubricant stock adjustments',
            'adjustments' => $adjustments,
            'q' => $q,
            'status' => $status,
            'statuses' => LubricantStockAdjustmentStatus::cases(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.lubricant-stock-adjustments._row-template',
            singular: 'lubricant-adjustment',
            builder: fn () => $this->buildAdjustmentsQuery($q, $status),
        );
    }

    protected function singularNoun(): string
    {
        return 'lubricant-adjustment';
    }

    private function buildAdjustmentsQuery(string $q, mixed $status)
    {
        return LubricantStockAdjustment::query()
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
        return view('admin.lubricant-stock-adjustments.create', [
            'title' => 'New lubricant stock adjustment',
            'lubricants' => Lubricant::query()->orderBy('name')->get(['id', 'name', 'lubricant_code']),
            'binLocations' => BinLocation::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'zone']),
        ]);
    }

    public function store(StoreLubricantStockAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $adjustment = DB::transaction(function () use ($data, $request) {
            $adj = LubricantStockAdjustment::create([
                'workshop_id' => $data['workshop_id'],
                'reference' => $data['reference'],
                'status' => LubricantStockAdjustmentStatus::Pending,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'requested_by' => $request->user()->getKey(),
            ]);

            $adj->items()->createMany($data['items']);

            return $adj;
        });

        AuditLog::record('lubricant-stock-adjustment.created', $adjustment, [
            'reference' => $adjustment->reference,
            'workshop_id' => $adjustment->workshop_id,
        ]);

        return redirect()->route('admin.lubricant-stock-adjustments.index')->with('status', 'Lubricant stock adjustment saved.');
    }

    public function show(LubricantStockAdjustment $lubricantStockAdjustment): View
    {
        $lubricantStockAdjustment->load([
            'requester', 'approver',
            'items.lubricant:id,name,lubricant_code',
            'items.bin:id,code',
        ]);

        return view('admin.lubricant-stock-adjustments.show', [
            'title' => $lubricantStockAdjustment->reference,
            'adjustment' => $lubricantStockAdjustment,
        ]);
    }

    public function approve(Request $request, LubricantStockAdjustment $lubricantStockAdjustment): RedirectResponse
    {
        if ($lubricantStockAdjustment->status !== LubricantStockAdjustmentStatus::Pending) {
            return back()->withErrors(['adjustment' => 'Only pending adjustments can be approved.']);
        }

        DB::transaction(function () use ($lubricantStockAdjustment, $request) {
            foreach ($lubricantStockAdjustment->items as $item) {
                LubricantStockMovement::create([
                    'workshop_id' => $lubricantStockAdjustment->workshop_id,
                    'lubricant_id' => $item->lubricant_id,
                    'bin_id' => $item->bin_id,
                    'user_id' => $request->user()->getKey(),
                    'lubricant_inventory_item_id' => $item->lubricant_inventory_item_id,
                    'type' => StockMovementType::ManualAdjustment,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'reference_type' => LubricantStockAdjustment::class,
                    'reference_id' => $lubricantStockAdjustment->id,
                    'reason' => $item->reason ?? $lubricantStockAdjustment->reason,
                    'occurred_at' => now(),
                ]);

                if ($item->lubricant_inventory_item_id) {
                    $item->lubricantInventoryItem?->increment('quantity', (float) $item->quantity);
                }
            }

            $lubricantStockAdjustment->update([
                'status' => LubricantStockAdjustmentStatus::Approved,
                'approved_by' => $request->user()->getKey(),
                'approved_at' => now(),
            ]);
        });

        AuditLog::record('lubricant-stock-adjustment.approved', $lubricantStockAdjustment, [
            'reference' => $lubricantStockAdjustment->reference,
        ]);

        return back()->with('status', "Adjustment {$lubricantStockAdjustment->reference} approved.");
    }

    public function reject(Request $request, LubricantStockAdjustment $lubricantStockAdjustment): RedirectResponse
    {
        if ($lubricantStockAdjustment->status !== LubricantStockAdjustmentStatus::Pending) {
            return back()->withErrors(['adjustment' => 'Only pending adjustments can be rejected.']);
        }

        $lubricantStockAdjustment->update([
            'status' => LubricantStockAdjustmentStatus::Rejected,
            'approved_by' => $request->user()->getKey(),
            'approved_at' => now(),
        ]);

        AuditLog::record('lubricant-stock-adjustment.rejected', $lubricantStockAdjustment, [
            'reference' => $lubricantStockAdjustment->reference,
        ]);

        return back()->with('status', "Adjustment {$lubricantStockAdjustment->reference} rejected.");
    }
}
