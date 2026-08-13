<?php

namespace App\Models;

use Database\Factories\PartCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable(['workshop_id', 'name', 'slug', 'description'])]
class PartCategory extends Model
{
    /** @use HasFactory<PartCategoryFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class, 'category_id');
    }
}
