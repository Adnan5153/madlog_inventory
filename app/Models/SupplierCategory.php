<?php

namespace App\Models;

use Database\Factories\SupplierCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Optional grouping for suppliers within a workshop. Administrators
 * can create these to organize procurement (e.g. OEM, Aftermarket,
 * Electrical, Fluids). Workshop-scoped.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['workshop_id', 'name', 'code', 'description', 'is_active'])]
class SupplierCategory extends Model
{
    /** @use HasFactory<SupplierCategoryFactory> */
    use HasFactory, Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}