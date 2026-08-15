<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Services\Inventory\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(protected StockAdjustmentService $service) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $adjustments = StockAdjustment::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('adjustment_number', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with(['requester:id,name', 'approver:id,name'])
            ->withCount('items')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stock-adjustments.index', [
            'title' => 'Stock adjustments',
            'adjustments' => $adjustments,
            'q' => $q,
            'status' => $status,
            'statuses' => [
                StockAdjustment::STATUS_DRAFT,
                StockAdjustment::STATUS_PENDING,
                StockAdjustment::STATUS_APPROVED,
                StockAdjustment::STATUS_REJECTED,
                StockAdjustment::STATUS_APPLIED,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.stock-adjustments.create', [
            'title' => 'New stock adjustment',
            'items' => InventoryItem::query()
                ->with('part:id,name,sku', 'bin:id,code')
                ->orderBy('id')
                ->limit(200)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:cycle_count,shrinkage,damage,found,manual'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer'],
            'items.*.adjustment_quantity' => ['required', 'numeric'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $adj = $this->service->create(
                $request->user(),
                $request->user()->workshop_id,
                $data['reason'],
                $data['notes'] ?? null,
                $data['items'],
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['adjustment' => $e->getMessage()]);
        }

        return redirect()->route('admin.stock-adjustments.show', $adj)->with('status', 'Stock adjustment saved.');
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $stockAdjustment->load(['requester', 'approver', 'items.inventoryItem.part', 'items.inventoryItem.bin']);

        return view('admin.stock-adjustments.show', [
            'title' => $stockAdjustment->adjustment_number,
            'adjustment' => $stockAdjustment,
        ]);
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->service->approve($stockAdjustment, $request->user());
            $this->service->apply($stockAdjustment, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }

        return back()->with('status', "Adjustment {$stockAdjustment->adjustment_number} approved and applied.");
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $reason = (string) $request->input('reason', '');
        try {
            $this->service->reject($stockAdjustment, $request->user(), $reason);
        } catch (DomainException $e) {
            return back()->withErrors(['adjustment' => $e->getMessage()]);
        }

        return back()->with('status', "Adjustment {$stockAdjustment->adjustment_number} rejected.");
    }
}
