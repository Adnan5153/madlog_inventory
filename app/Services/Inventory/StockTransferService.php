<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inter-bin stock transfer lifecycle service.
 *
 *   draft → in_transit → received   (or cancelled)
 *
 * On dispatch, source bucket quantities are decremented (transfer_out
 * movement). On receipt, the destination bucket is incremented
 * (transfer_in movement). The two movements balance to keep the
 * inventory ledger consistent.
 */
class StockTransferService
{
    public function __construct(protected StockMovementService $movements)
    {
    }

    /**
     * @param array<int, array{part_id:int, quantity:float, batch_number?:string|null}> $items
     */
    public function create(User $actor, int $workshopId, ?int $sourceBinId, int $destinationBinId, ?string $notes, array $items): StockTransfer
    {
        if ($sourceBinId === $destinationBinId) {
            throw new DomainException('Source and destination bins must differ.');
        }
        if (empty($items)) {
            throw new DomainException('Stock transfer must include at least one line.');
        }

        return DB::transaction(function () use ($actor, $workshopId, $sourceBinId, $destinationBinId, $notes, $items) {
            $t = StockTransfer::create([
                'workshop_id' => $workshopId,
                'transfer_number' => $this->nextNumber($workshopId),
                'status' => StockTransfer::STATUS_DRAFT,
                'source_bin_id' => $sourceBinId,
                'destination_bin_id' => $destinationBinId,
                'transferred_by' => $actor->id,
                'notes' => $notes,
            ]);

            foreach ($items as $line) {
                StockTransferItem::create([
                    'stock_transfer_id' => $t->id,
                    'part_id' => $line['part_id'],
                    'batch_number' => $line['batch_number'] ?? null,
                    'quantity' => $line['quantity'],
                ]);
            }

            AuditLog::record('stock_transfer.created', $t, [
                'transfer_number' => $t->transfer_number,
                'source_bin_id' => $t->source_bin_id,
                'destination_bin_id' => $t->destination_bin_id,
            ]);

            return $t->fresh('items');
        });
    }

    /**
     * Move a draft transfer to in_transit and decrement the source buckets.
     *
     * @throws DomainException
     */
    public function dispatch(StockTransfer $t, User $actor): StockTransfer
    {
        if ($t->status !== StockTransfer::STATUS_DRAFT) {
            throw new DomainException("Only draft transfers can be dispatched. Current status: {$t->status}.");
        }
        if (! $t->source_bin_id) {
            throw new DomainException('Cannot dispatch a transfer without a source bin.');
        }

        return DB::transaction(function () use ($t, $actor) {
            foreach ($t->items()->get() as $line) {
                /** @var StockTransferItem $line */
                $bucket = InventoryItem::query()
                    ->where('workshop_id', $t->workshop_id)
                    ->where('part_id', $line->part_id)
                    ->where('bin_id', $t->source_bin_id)
                    ->when(
                        $line->batch_number !== null,
                        fn ($q) => $q->where('batch_number', $line->batch_number),
                        fn ($q) => $q->whereNull('batch_number'),
                    )
                    ->first();

                if (! $bucket) {
                    throw new DomainException("No stock for part {$line->part_id} in source bin.");
                }
                if ((float) $bucket->quantity < (float) $line->quantity) {
                    throw new DomainException("Insufficient stock in source bin (have {$bucket->quantity}, need {$line->quantity}).");
                }

                $this->movements->record(
                    StockMovementType::TransferOut,
                    $bucket,
                    -1.0 * (float) $line->quantity,
                    $actor,
                    null,
                    "Transfer {$t->transfer_number} out",
                    $t,
                );
            }

            $t->status = StockTransfer::STATUS_IN_TRANSIT;
            $t->dispatched_at = now();
            $t->save();

            AuditLog::record('stock_transfer.dispatched', $t, [
                'transfer_number' => $t->transfer_number,
                'dispatched_by' => $actor->id,
            ]);

            return $t->fresh();
        });
    }

    public function receive(StockTransfer $t, User $receiver): StockTransfer
    {
        if ($t->status !== StockTransfer::STATUS_IN_TRANSIT) {
            throw new DomainException("Only in-transit transfers can be received. Current status: {$t->status}.");
        }

        return DB::transaction(function () use ($t, $receiver) {
            foreach ($t->items()->get() as $line) {
                /** @var StockTransferItem $line */
                $bucket = InventoryItem::query()
                    ->where('workshop_id', $t->workshop_id)
                    ->where('part_id', $line->part_id)
                    ->where('bin_id', $t->destination_bin_id)
                    ->when(
                        $line->batch_number !== null,
                        fn ($q) => $q->where('batch_number', $line->batch_number),
                        fn ($q) => $q->whereNull('batch_number'),
                    )
                    ->first();

                if (! $bucket) {
                    $bucket = InventoryItem::create([
                        'workshop_id' => $t->workshop_id,
                        'part_id' => $line->part_id,
                        'bin_id' => $t->destination_bin_id,
                        'batch_number' => $line->batch_number,
                        'quantity' => 0,
                        'cost_price' => 0,
                    ]);
                }

                $this->movements->record(
                    StockMovementType::TransferIn,
                    $bucket,
                    (float) $line->quantity,
                    $receiver,
                    null,
                    "Transfer {$t->transfer_number} in",
                    $t,
                );
            }

            $t->status = StockTransfer::STATUS_RECEIVED;
            $t->received_by = $receiver->id;
            $t->received_at = now();
            $t->save();

            AuditLog::record('stock_transfer.received', $t, [
                'transfer_number' => $t->transfer_number,
                'received_by' => $receiver->id,
            ]);

            return $t->fresh();
        });
    }

    protected function nextNumber(int $workshopId): string
    {
        $format = setting('stock_transfer.number_format', 'TRF-{YYYY}-{NNNN}', $workshopId);
        $year = date('Y');
        $existing = StockTransfer::query()
            ->where('workshop_id', $workshopId)
            ->where('transfer_number', 'like', "%{$year}%")
            ->count();

        $number = str_replace(
            ['{YYYY}', '{NNNN}'],
            [$year, str_pad((string) ($existing + 1), 4, '0', STR_PAD_LEFT)],
            $format
        );

        if (StockTransfer::query()->where('workshop_id', $workshopId)->where('transfer_number', $number)->exists()) {
            $number .= '-' . strtoupper(Str::random(4));
        }

        return $number;
    }
}