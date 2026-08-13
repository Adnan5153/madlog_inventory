<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Operational department within a workshop — e.g. "Maintenance",
 * "Engineering", "Marine". Workshop-scoped so different workshops
 * can have different organizational charts.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property int|null $manager_id
 * @property bool $is_active
 */
#[Fillable(['workshop_id', 'name', 'code', 'description', 'manager_id', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory, Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function equipment(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}