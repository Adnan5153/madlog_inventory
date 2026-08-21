<?php

namespace App\Enums;

/**
 * Status of a single tool-checkout event. `Open` means the tool is
 * currently with the user; `Closed` means it has been returned;
 * `Overdue` is a derived marker for open checkouts whose
 * `expected_return_at` has passed.
 */
enum ToolCheckoutStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Overdue = 'overdue';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Overdue => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'primary',
            self::Closed => 'secondary',
            self::Overdue => 'danger',
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
