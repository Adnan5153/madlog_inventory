<?php

namespace App\Enums;

/**
 * Base chemistry / composition of a lubricant. Stored as a string on the
 * lubricants table; the values are stable and used in form validation,
 * reports and CSV exports.
 */
enum LubricantType: string
{
    case Mineral = 'mineral';
    case SemiSynthetic = 'semi_synthetic';
    case Synthetic = 'synthetic';
    case FullySynthetic = 'fully_synthetic';
    case Bio = 'bio';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Mineral => 'Mineral',
            self::SemiSynthetic => 'Semi-Synthetic',
            self::Synthetic => 'Synthetic',
            self::FullySynthetic => 'Fully Synthetic',
            self::Bio => 'Bio-based',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FullySynthetic => 'success',
            self::Synthetic => 'primary',
            self::SemiSynthetic => 'info',
            self::Mineral => 'secondary',
            self::Bio => 'success',
            self::Other => 'dark',
        };
    }

    public function isSynthetic(): bool
    {
        return in_array($this, [self::Synthetic, self::FullySynthetic, self::SemiSynthetic], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
