<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stock adjustment lifecycle service.
 *
 *   draft/pending → approved → applied
 *                 ↘
 *                   rejected (terminal)
 *
 * Approval is gated by `inventory.require_adjustment_approval` and the
 * requester cannot be the approver.
 */
class StockAdjustmentService
{
    public function __construct(protected StockMovementService $movements)
    {
    }

    /**
     * @param array<int, array{inventory_item_id:int, adjustment_quantity:float, unit_cost?:float|null}> $items
     */
    public function create(User $actor, int $workshopId, string $reason, ?string $notes, array $items): StockAdjustment
    {
        if (empty($items)) {
            throw new DomainException('Stock adjustment must include at least one line.');
        }

        return DB::transaction(function () use ($actor, $workshopId, $reason, $notes, $items) {
            $requireApproval = (bool) setting('inventory.require_adjustment_approval', true, $workshopId);

            $adj = StockAdjustment::create([
                'workshop_id' => $workshopId,
                'adjustment_number' => $this->nextNumber($workshopId),
                'status' => $requireApproval ? StockAdjustment::STATUS_PENDING : StockAdjustment::STATUS_APPROVED,
                'reason' => $reason,
                'notes' => $notes,
                'requested_by' => $actor->id,
                'approved_by' => $requireApproval ? null : $actor->id,
                'approved_at' => $requireApproval ? null : now(),
            ]);

            foreach ($items as $line) {
                $item = InventoryItem::query()->where('workshop_id', $workshopId)->find($line['inventory_item_id']);
                if (! $item) {
                    throw new DomainException("Inventory item #{$line['inventory_item_id']} not found in this workshop.");
                }

                $before = (float) $item->quantity;
                $delta = (float) $line['adjustment_quantity'];

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adj->id,
                    'inventory_item_id' => $item->id,
                    'before_quantity' => $before,
                    'adjustment_quantity' => $delta,
                    'after_quantity' => $before + $delta,
                    'unit_cost' => $line['unit_cost'] ?? null,
                ]);
            }

            AuditLog::record('stock_adjustment.created', $adj, [
                'adjustment_number' => $adj->adjustment_number,
                'reason' => $adj->reason,
                'status' => $adj->status,
            ]);

            // If approval is not required, apply immediately.
            if (! $requireApproval) {
                return $this->apply($adj, $actor);
            }

            return $adj->fresh('items');
        });
    }

    public function approve(StockAdjustment $adj, User $approver): StockAdjustment
    {
        if ($adj->status !== StockAdjustment::STATUS_PENDING) {
            throw new DomainException("Only pending adjustments can be approved. Current status: {$adj->status}.");
        }

        if ($approver->id === $adj->requested_by) {
            throw new DomainException('An adjustment cannot be approved by its requester.');
        }

        return DB::transaction(function () use ($adj, $approver) {
            $adj->status = StockAdjustment::STATUS_APPROVED;
            $adj->approved_by = $approver->id;
            $adj->approved_at = now();
            $adj->save();

            AuditLog::record('stock_adjustment.approved', $adj, [
                'adjustment_number' => $adj->adjustment_number,
                'approved_by' => $approver->id,
            ]);

            return $adj->fresh();
        });
    }

    public function reject(StockAdjustment $adj, User $approver, ?string $reason = null): StockAdjustment
    {
        if (! in_array($adj->status, [StockAdjustment::STATUS_PENDING, StockAdjustment::STATUS_APPROVED], true)) {
            throw new DomainException("Cannot reject an adjustment in status: {$adj->status}.");
        }

        return DB::transaction(function () use ($adj, $approver, $reason) {
            $adj->status = StockAdjustment::STATUS_REJECTED;
            $adj->notes = trim(($adj->notes ?? '') . "\n[Reject reason] " . ($reason ?? 'no reason given'));
            $adj->save();

            AuditLog::record('stock_adjustment.rejected', $adj, [
                'adjustment_number' => $adj->adjustment_number,
                'rejected_by' => $approver->id,
                'reason' => $reason,
            ]);

            return $adj->fresh();
        });
    }

    public function apply(StockAdjustment $adj, User $actor): StockAdjustment
    {
        if ($adj->status !== StockAdjustment::STATUS_APPROVED) {
            throw new DomainException("Only approved adjustments can be applied. Current status: {$adj->status}.");
        }

        return DB::transaction(function () use ($adj, $actor) {
            foreach ($adj->items()->get() as $line) {
                /** @var StockAdjustmentItem $line */
                $item = InventoryItem::query()->find($line->inventory_item_id);
                if (! $item) {
                    throw new DomainException("Inventory item #{$line->inventory_item_id} no longer exists.");
                }
                $type = $line->adjustment_quantity >= 0
                    ? StockMovementType::Adjustment
                    : StockMovementType::ManualAdjustment;

                $this->movements->record(
                    $type,
                    $item,
                    (float) $line->adjustment_quantity,
                    $actor,
                    $line->unit_cost !== null ? (float) $line->unit_cost : null,
                    "Adjustment {$adj->adjustment_number} ({$adj->reason})",
                    $adj,
                );
            }

            $adj->status = StockAdjustment::STATUS_APPLIED;
            $adj->applied_at = now();
            $adj->save();

            AuditLog::record('stock_adjustment.applied', $adj, [
                'adjustment_number' => $adj->adjustment_number,
                'applied_by' => $actor->id,
            ]);

            return $adj->fresh('items');
        });
    }

    protected function nextNumber(int $workshopId): string
    {
        $format = setting('stock_adjustment.number_format', 'ADJ-{YYYY}-{NNNN}', $workshopId);
        $year = date('Y');
        $existing = StockAdjustment::query()
            ->where('workshop_id', $workshopId)
            ->where('adjustment_number', 'like', "%{$year}%")
            ->count();

        $number = str_replace(
            ['{YYYY}', '{NNNN}'],
            [$year, str_pad((string) ($existing + 1), 4, '0', STR_PAD_LEFT)],
            $format
        );

        if (StockAdjustment::query()->where('workshop_id', $workshopId)->where('adjustment_number', $number)->exists()) {
            $number .= '-' . strtoupper(Str::random(4));
        }

        return $number;
    }
}