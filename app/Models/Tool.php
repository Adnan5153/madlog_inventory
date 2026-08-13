<?php

namespace App\Models;

use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical tool (not a part). Tracked because tool shrinkage is a
 * real cost at busy workshops. Each tool can be checked out to one user
 * at a time; an "open" checkout exists when returned_at IS NULL.
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $name
 * @property string|null $asset_tag
 * @property string|null $serial_number
 * @property int|null $bin_id
 * @property numeric $value
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable([
    'workshop_id',
    'name',
    'asset_tag',
    'serial_number',
    'bin_id',
    'value',
    'description',
    'is_active',
])]
class Tool extends Model
{
    /** @use HasFactory<ToolFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(ToolCheckout::class);
    }

    /**
     * The currently open checkout, if any.
     */
    public function currentCheckout(): HasOne
    {
        return $this->hasOne(ToolCheckout::class)
            ->whereNull('returned_at')
            ->latestOfMany('checked_out_at');
    }

    /**
     * Whether this tool is currently out (not in its bin).
     */
    public function isCheckedOut(): bool
    {
        return $this->currentCheckout()->exists();
    }
}