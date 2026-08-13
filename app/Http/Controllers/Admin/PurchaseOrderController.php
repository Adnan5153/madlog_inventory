<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReceivePurchaseOrderRequest;
use App\Http\Requests\Admin\StorePurchaseOrderRequest;
use App\Http\Requests\Admin\UpdatePurchaseOrderRequest;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Inventory\PurchaseOrderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PurchaseOrderService $service)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');

        $orders = PurchaseOrder::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('po_number', 'like', "%{$q}%")
                  ->orWhere('notes', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->when($supplierId, fn ($qb) => $qb->where('supplier_id', $supplierId))
            ->with(['supplier:id,name', 'creator:id,name', 'approver:id,name'])
            ->withCount('items')
            ->latest('order_date')
            ->paginate(20)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'title' => 'Purchase orders',
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'supplierId' => $supplierId,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => [
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_APPROVED,
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_CANCELLED,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.purchase-orders.create', [
            'title' => 'New purchase order',
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $po = new PurchaseOrder([
            'po_number' => $this->service->nextPoNumber($user->workshop_id),
            'workshop_id' => $user->workshop_id,
            'supplier_id' => $data['supplier_id'],
            'created_by' => $user->id,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $subtotal = 0.0;
        foreach ($data['items'] as $line) {
            $subtotal += (float) $line['quantity_ordered'] * (float) $line['unit_cost'];
        }
        $po->subtotal = $subtotal;
        $po->tax = 0;
        $po->total = $subtotal;
        $po->save();

        foreach ($data['items'] as $line) {
            $po->items()->create([
                'part_id' => $line['part_id'],
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => (float) $line['quantity_ordered'] * (float) $line['unit_cost'],
            ]);
        }

        AuditLog::record('purchase_order.created', $po, [
            'po_number' => $po->po_number,
            'supplier_id' => $po->supplier_id,
            'total' => $po->total,
        ]);

        return redirect()->route('admin.purchase-orders.show', $po)->with('status', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['supplier', 'creator', 'approver', 'items.part']);
        $purchaseOrder->load(['goodsReceipts' => fn ($q) => $q->latest('received_at')->limit(5), 'goodsReceipts.items']);

        return view('admin.purchase-orders.show', [
            'title' => $purchaseOrder->po_number,
            'order' => $purchaseOrder,
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->isDraft(), 422, 'Only draft purchase orders can be edited.');

        $purchaseOrder->load('items.part');

        return view('admin.purchase-orders.edit', [
            'title' => 'Edit purchase order',
            'order' => $purchaseOrder,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        abort_unless($purchaseOrder->isDraft(), 422, 'Only draft purchase orders can be edited.');

        $data = $request->validated();
        $purchaseOrder->fill([
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $subtotal = 0.0;
        foreach ($data['items'] as $line) {
            $subtotal += (float) $line['quantity_ordered'] * (float) $line['unit_cost'];
        }
        $purchaseOrder->subtotal = $subtotal;
        $purchaseOrder->total = $subtotal;
        $purchaseOrder->save();

        $purchaseOrder->items()->delete();
        foreach ($data['items'] as $line) {
            $purchaseOrder->items()->create([
                'part_id' => $line['part_id'],
                'quantity_ordered' => $line['quantity_ordered'],
                'unit_cost' => $line['unit_cost'],
                'line_total' => (float) $line['quantity_ordered'] * (float) $line['unit_cost'],
            ]);
        }

        AuditLog::record('purchase_order.updated', $purchaseOrder, ['po_number' => $purchaseOrder->po_number]);

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('status', 'Purchase order updated.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);
        abort_unless(! $purchaseOrder->isFullyReceived(), 422, 'Cannot delete a fully-received purchase order.');

        AuditLog::record('purchase_order.deleted', $purchaseOrder, ['po_number' => $purchaseOrder->po_number]);
        $purchaseOrder->delete();

        return redirect()->route('admin.purchase-orders.index')->with('status', 'Purchase order archived.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        try {
            $this->service->submit($purchaseOrder, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return back()->with('status', "Purchase order {$purchaseOrder->po_number} submitted for approval.");
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('approve', $purchaseOrder);
        try {
            $this->service->approve($purchaseOrder, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return back()->with('status', "Purchase order {$purchaseOrder->po_number} approved.");
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);
        $reason = (string) $request->input('reason', '');
        try {
            $this->service->cancel($purchaseOrder, $request->user(), $reason);
        } catch (DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return back()->with('status', "Purchase order {$purchaseOrder->po_number} cancelled.");
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('receive', $purchaseOrder);

        try {
            $grn = $this->service->receive(
                $purchaseOrder,
                $request->user(),
                $request->validated('items'),
                $request->validated('bin_location_id'),
                $request->validated('supplier_invoice_number'),
                $request->validated('notes'),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['po' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.purchase-orders.show', $purchaseOrder)
            ->with('status', "Goods received: {$grn->grn_number}.");
    }
}