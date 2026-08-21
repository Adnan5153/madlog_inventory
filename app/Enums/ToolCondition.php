<?php

namespace App\Enums;

/**
 * Physical condition of a tool — what it can do physically, distinct
 * from `ToolStatus` which is the operational lifecycle. A brand-new
 * tool is `Excellent` in condition but `Available` in status.
 */
enum ToolCondition: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Damaged => 'Damaged',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Excellent => 'success',
            self::Good => 'primary',
            self::Fair => 'info',
            self::Damaged, self::Critical => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
