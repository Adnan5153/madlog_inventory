<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Purchase-order lifecycle service.
 *
 * State machine:
 *   draft → submitted → approved → partially_received → received
 *                     ↘                        ↘
 *                       cancelled              cancelled
 *
 * All write paths run inside DB::transaction() and emit audit log rows.
 * Receiving a PO writes one StockMovement row per line and creates or
 * updates an InventoryItem bucket for the chosen bin.
 */
class PurchaseOrderService
{
    /**
     * Build a unique PO number from the configured format.
     * The default format is `PO-{YYYY}-{NNNN}`; NNNN is a zero-padded
     * count for the current year, scoped to the workshop.
     */
    public function nextPoNumber(int $workshopId): string
    {
        $format = setting('po.number_format', 'PO-{YYYY}-{NNNN}', $workshopId);
        $year = date('Y');
        $existing = PurchaseOrder::query()
            ->where('workshop_id', $workshopId)
            ->where('po_number', 'like', "%{$year}%")
            ->count();

        $number = str_replace(
            ['{YYYY}', '{NNNN}'],
            [$year, str_pad((string) ($existing + 1), 4, '0', STR_PAD_LEFT)],
            $format
        );

        // Defensive: if the configured format yields a duplicate, append
        // a short random suffix rather than crashing the request.
        if (PurchaseOrder::query()->where('workshop_id', $workshopId)->where('po_number', $number)->exists()) {
            $number .= '-'.strtoupper(Str::random(4));
        }

        return $number;
    }

    /**
     * Move a draft PO to submitted. Records the actor in the audit log.
     *
     * @throws DomainException
     */
    public function submit(PurchaseOrder $po, User $actor): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new DomainException("Only draft purchase orders can be submitted. Current status: {$po->status}.");
        }

        if ($po->items()->count() === 0) {
            throw new DomainException('Cannot submit a purchase order with no line items.');
        }

        return DB::transaction(function () use ($po, $actor) {
            $po->status = PurchaseOrder::STATUS_SUBMITTED;
            $po->save();

            AuditLog::record('purchase_order.submitted', $po, [
                'po_number' => $po->po_number,
                'submitted_by' => $actor->id,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Approve a submitted PO. Optionally gated by an admin-only setting;
     * the policy in PurchaseOrderPolicy already enforces admin role.
     *
     * @throws DomainException
     */
    public function approve(PurchaseOrder $po, User $approver): PurchaseOrder
    {
        if ($po->status !== PurchaseOrder::STATUS_SUBMITTED) {
            throw new DomainException("Only submitted purchase orders can be approved. Current status: {$po->status}.");
        }

        return DB::transaction(function () use ($po, $approver) {
            $po->status = PurchaseOrder::STATUS_APPROVED;
            $po->approved_by = $approver->id;
            $po->save();

            AuditLog::record('purchase_order.approved', $po, [
                'po_number' => $po->po_number,
                'approved_by' => $approver->id,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Cancel a PO that has not yet been fully received.
     *
     * @throws DomainException
     */
    public function cancel(PurchaseOrder $po, User $actor, ?string $reason = null): PurchaseOrder
    {
        if (! $po->isCancellable()) {
            throw new DomainException("Cannot cancel purchase order in status: {$po->status}.");
        }

        return DB::transaction(function () use ($po, $actor, $reason) {
            $po->status = PurchaseOrder::STATUS_CANCELLED;
            $po->notes = trim(($po->notes ?? '')."\n[Cancel reason] ".($reason ?? 'no reason given'));
            $po->save();

            AuditLog::record('purchase_order.cancelled', $po, [
                'po_number' => $po->po_number,
                'cancelled_by' => $actor->id,
                'reason' => $reason,
            ]);

            return $po->fresh();
        });
    }

    /**
     * Receive goods against an approved PO. Each line must include the
     * quantity being received, the destination bin, and an optional batch
     * label. The service:
     *   - validates received quantities against remaining PO quantities
     *   - creates a GoodsReceipt + line items
     *   - appends one StockMovement (receipt) per line
     *   - creates/updates the matching InventoryItem bucket and increments
     *     its quantity
     *   - refreshes the parent PO status via PurchaseOrder::refreshReceiptStatus
     *
     * @param array<int, array{
     *   purchase_order_item_id: int,
     *   quantity_received: float,
     *   bin_location_id: ?int,
     *   batch_number: ?string,
     *   unit_cost: ?float,
     *   expires_at: ?string,
     *   damaged_quantity?: float
     * }> $lines
     *
     * @throws DomainException
     */
    public function receive(
        PurchaseOrder $po,
        User $receiver,
        array $lines,
        ?int $defaultBinId = null,
        ?string $supplierInvoiceNumber = null,
        ?string $notes = null,
    ): GoodsReceipt {
        if (! in_array($po->status, [
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ], true)) {
            throw new DomainException("Only approved purchase orders can receive goods. Current status: {$po->status}.");
        }

        if (empty($lines)) {
            throw new DomainException('Receive payload must include at least one line item.');
        }

        return DB::transaction(function () use ($po, $receiver, $lines, $defaultBinId, $supplierInvoiceNumber, $notes) {
            // Validate every line against remaining PO quantity and resolve
            // the related PO item and part.
            $resolved = [];
            foreach ($lines as $idx => $line) {
                $poi = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $po->id)
                    ->where('id', $line['purchase_order_item_id'] ?? 0)
                    ->first();

                if (! $poi) {
                    throw new DomainException("Line {$idx} does not belong to purchase order {$po->po_number}.");
                }

                $received = (float) ($line['quantity_received'] ?? 0);
                if ($received <= 0) {
                    throw new DomainException("Line {$idx} received quantity must be positive.");
                }

                $remaining = $poi->remainingQuantity();
                if ($received > $remaining + 0.0001) {
                    throw new DomainException("Line {$idx} received ({$received}) exceeds remaining PO quantity ({$remaining}).");
                }

                $binId = $line['bin_location_id'] ?? $defaultBinId;
                if (! $binId) {
                    throw new DomainException("Line {$idx} requires a destination bin.");
                }

                $resolved[] = [
                    'poi' => $poi,
                    'quantity_received' => $received,
                    'damaged_quantity' => (float) ($line['damaged_quantity'] ?? 0),
                    'bin_location_id' => $binId,
                    'batch_number' => $line['batch_number'] ?? null,
                    'unit_cost' => isset($line['unit_cost']) ? (float) $line['unit_cost'] : (float) $poi->unit_cost,
                    'expires_at' => $line['expires_at'] ?? null,
                ];
            }

            // The status flag on the parent GRN: full = received, mixed
            // delivery that doesn't fully cover all PO lines = partial.
            $allFull = collect($resolved)->every(fn ($r) => abs($r['quantity_received'] - $r['poi']->remainingQuantity()) < 0.0001);

            $grn = GoodsReceipt::create([
                'workshop_id' => $po->workshop_id,
                'purchase_order_id' => $po->id,
                'bin_location_id' => $defaultBinId,
                'received_by' => $receiver->id,
                'grn_number' => $this->nextGrnNumber($po->workshop_id),
                'supplier_invoice_number' => $supplierInvoiceNumber,
                'status' => $allFull ? GoodsReceipt::STATUS_RECEIVED : GoodsReceipt::STATUS_PARTIAL,
                'received_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($resolved as $r) {
                /** @var PurchaseOrderItem $poi */
                $poi = $r['poi'];
                $goodQty = max(0.0, $r['quantity_received'] - $r['damaged_quantity']);

                // 1) GRN line
                $grnItem = $grn->items()->create([
                    'purchase_order_item_id' => $poi->id,
                    'part_id' => $poi->part_id,
                    'bin_location_id' => $r['bin_location_id'],
                    'quantity_ordered' => $poi->quantity_ordered,
                    'quantity_received' => $r['quantity_received'],
                    'damaged_quantity' => $r['damaged_quantity'],
                    'batch_number' => $r['batch_number'],
                    'unit_cost' => $r['unit_cost'],
                    'expires_at' => $r['expires_at'],
                ]);

                // 2) Stock movement ledger row
                StockMovement::create([
                    'workshop_id' => $po->workshop_id,
                    'part_id' => $poi->part_id,
                    'bin_id' => $r['bin_location_id'],
                    'user_id' => $receiver->id,
                    'type' => StockMovementType::Receipt,
                    'quantity' => $goodQty,
                    'unit_cost' => $r['unit_cost'],
                    'reference_type' => GoodsReceipt::class,
                    'reference_id' => $grn->id,
                    'reason' => "PO {$po->po_number} GRN {$grn->grn_number}",
                    'occurred_at' => now(),
                ]);

                // 3) Find or create the inventory bucket for (part, bin, batch)
                $bucket = InventoryItem::query()
                    ->where('workshop_id', $po->workshop_id)
                    ->where('part_id', $poi->part_id)
                    ->where('bin_id', $r['bin_location_id'])
                    ->when(
                        $r['batch_number'] !== null,
                        fn ($q) => $q->where('batch_number', $r['batch_number']),
                        fn ($q) => $q->whereNull('batch_number'),
                    )
                    ->first();

                if (! $bucket) {
                    $bucket = InventoryItem::create([
                        'workshop_id' => $po->workshop_id,
                        'part_id' => $poi->part_id,
                        'bin_id' => $r['bin_location_id'],
                        'supplier_id' => $po->supplier_id,
                        'batch_number' => $r['batch_number'],
                        'quantity' => 0,
                        'cost_price' => $r['unit_cost'],
                        'expires_at' => $r['expires_at'],
                    ]);
                }

                $bucket->quantity = (float) $bucket->quantity + $goodQty;
                // Weighted-average cost update.
                $existingQty = (float) $bucket->quantity - $goodQty;
                if ($existingQty > 0) {
                    $bucket->cost_price = (
                        ((float) $bucket->cost_price * $existingQty)
                        + ($r['unit_cost'] * $goodQty)
                    ) / (float) $bucket->quantity;
                } else {
                    $bucket->cost_price = $r['unit_cost'];
                }
                $bucket->save();

                // 4) PO line progress
                $poi->quantity_received = (float) $poi->quantity_received + $r['quantity_received'];
                $poi->save();
            }

            // 5) Refresh parent PO status to received/partially_received
            $po->refreshReceiptStatus();

            AuditLog::record('purchase_order.received', $po, [
                'po_number' => $po->po_number,
                'grn_number' => $grn->grn_number,
                'received_by' => $receiver->id,
                'new_status' => $po->status,
            ]);

            return $grn->fresh('items');
        });
    }

    protected function nextGrnNumber(int $workshopId): string
    {
        $format = setting('grn.number_format', 'GRN-{YYYY}-{NNNN}', $workshopId);
        $year = date('Y');
        $existing = GoodsReceipt::query()
            ->where('workshop_id', $workshopId)
            ->where('grn_number', 'like', "%{$year}%")
            ->count();

        $number = str_replace(
            ['{YYYY}', '{NNNN}'],
            [$year, str_pad((string) ($existing + 1), 4, '0', STR_PAD_LEFT)],
            $format
        );

        if (GoodsReceipt::query()->where('workshop_id', $workshopId)->where('grn_number', $number)->exists()) {
            $number .= '-'.strtoupper(Str::random(4));
        }

        return $number;
    }
}
