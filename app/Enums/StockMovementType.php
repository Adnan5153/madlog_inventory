<?php

namespace App\Enums;

/**
 * Semantic types for stock_movements ledger rows. The signed quantity on
 * the row tells you direction; the type tells you *why*.
 *
 * Corrections are made by posting a reversing movement of the same type,
 * never by updating or deleting existing rows.
 */
enum StockMovementType: string
{
    /** A purchase order has been received into a bin. */
    case Receipt = 'receipt';

    /** Parts issued from stock to a job card. */
    case Issue = 'issue';

    /** Parts returned from a job card back to a bin. */
    case Return = 'return';

    /** Inter-bin transfer out (paired with transfer_in on the destination bin). */
    case TransferOut = 'transfer_out';

    /** Inter-bin transfer in (paired with transfer_out on the source bin). */
    case TransferIn = 'transfer_in';

    /** Cycle count adjustment (positive or negative). */
    case Adjustment = 'adjustment';

    /** Shrinkage write-off (negative quantity only). */
    case Shrinkage = 'shrinkage';

    /** Manual adjustment (requires reason; for one-off corrections). */
    case ManualAdjustment = 'manual_adjustment';

    /**
     * Whether this movement type requires a human-written reason in the
     * `reason` column.
     */
    public function requiresReason(): bool
    {
        return $this === self::ManualAdjustment
            || $this === self::Shrinkage
            || $this === self::Adjustment;
    }

    /**
     * Whether this movement represents stock coming into a bin.
     */
    public function isInbound(): bool
    {
        return match ($this) {
            self::Receipt, self::TransferIn, self::Return => true,
            default => false,
        };
    }

    /**
     * Whether this movement represents stock leaving a bin.
     */
    public function isOutbound(): bool
    {
        return match ($this) {
            self::Issue, self::TransferOut, self::Shrinkage => true,
            default => false,
        };
    }

    /**
     * Human-readable label for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Issue => 'Issue',
            self::Return => 'Return',
            self::TransferOut => 'Transfer Out',
            self::TransferIn => 'Transfer In',
            self::Adjustment => 'Adjustment',
            self::Shrinkage => 'Shrinkage',
            self::ManualAdjustment => 'Manual Adjustment',
        };
    }
}
