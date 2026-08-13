<?php

namespace App\Models;

use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string $slug
 */
#[Fillable(['workshop_id', 'name', 'slug'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }
}
