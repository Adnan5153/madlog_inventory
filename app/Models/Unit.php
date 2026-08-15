<?php

namespace App\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit of measure. Master-data table shared across all workshops
 * (units are not workshop-scoped — kg is kg everywhere). The
 * `is_active` flag is the only lever administrators have to hide
 * a unit without losing historical references.
 *
 * @property int $id
 * @property string $name
 * @property string $short_code
 * @property string|null $description
 * @property int $decimal_precision
 * @property bool $is_active
 */
#[Fillable(['name', 'short_code', 'description', 'decimal_precision', 'is_active'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'decimal_precision' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function binLocations(): HasMany
    {
        return $this->hasMany(BinLocation::class);
    }

    /**
     * Scope to only active units (default for dropdowns/option lists).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Format a numeric quantity according to this unit's precision.
     */
    public function format(float|string $qty): string
    {
        return number_format((float) $qty, $this->decimal_precision);
    }
}
