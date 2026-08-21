<?php

namespace App\Enums;

/**
 * Bucketed stock status for any trackable SKU (battery, part, etc.).
 * Computed from on-hand quantity vs. reorder threshold.
 *
 *   out_of_stock  — quantity ≤ 0
 *   low_stock     — quantity > 0 but ≤ reorder threshold
 *   in_stock      — quantity > reorder threshold
 */
enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In stock',
            self::LowStock => 'Low stock',
            self::OutOfStock => 'Out of stock',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s) => $s->value, self::cases());
    }
}
