<?php

namespace App\Models;

use Database\Factories\ToolCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Operational category for tools (Hand Tools, Power Tools, Diagnostic,
 * Lifting Equipment, etc.). Workshop-scoped; deletion is refused while
 * any tool still references the category.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string|null $slug
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable([
    'workshop_id',
    'name',
    'slug',
    'description',
    'is_active',
])]
class ToolCategory extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<ToolCategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class, 'category_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('tool_categories.is_active', true);
    }
}
