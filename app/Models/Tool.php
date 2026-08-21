<?php

namespace App\Models;

use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use Database\Factories\ToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * A physical operational tool (torque wrench, scanner, jack, etc.).
 * Each tool is a single serialized asset — NOT bucketed stock. Lifecycle
 * columns (status, condition, holder) live directly on the row.
 *
 * Checkout/check-in events are recorded in `tool_checkouts` (one open
 * checkout per tool at a time, enforced by ToolCheckoutService).
 *
 * @property int $id
 * @property int $workshop_id
 * @property string $tool_code
 * @property string $name
 * @property int|null $category_id
 * @property string|null $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string|null $barcode
 * @property string|null $qr_code
 * @property string|null $description
 * @property string $condition
 * @property string $status
 * @property int|null $current_holder_user_id
 * @property bool $is_active
 * @property int|null $bin_id
 * @property int|null $supplier_id
 * @property Carbon|null $purchase_date
 * @property numeric $purchase_price
 * @property Carbon|null $warranty_expiry
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'tool_code',
    'name',
    'category_id',
    'brand',
    'model',
    'serial_number',
    'barcode',
    'qr_code',
    'description',
    'condition',
    'status',
    'current_holder_user_id',
    'is_active',
    'bin_id',
    'supplier_id',
    'purchase_date',
    'purchase_price',
    'warranty_expiry',
    'notes',
])]
class Tool extends Model
{
    use Concerns\BelongsToWorkshop;
    /** @use HasFactory<ToolFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'condition' => ToolCondition::class,
            'status' => ToolStatus::class,
            'is_active' => 'boolean',
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'purchase_price' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ToolCategory::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function binLocation(): BelongsTo
    {
        return $this->belongsTo(BinLocation::class, 'bin_id');
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_holder_user_id');
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(ToolCheckout::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(ToolMaintenanceRecord::class);
    }

    /**
     * The currently open checkout (the one with no `returned_at`).
     * Returns null when the tool is in storage.
     */
    public function currentCheckout(): HasOne
    {
        return $this->hasOne(ToolCheckout::class)
            ->whereNull('returned_at')
            ->ofMany('checked_out_at', 'max');
    }

    public function isCheckedOut(): bool
    {
        return $this->current_holder_user_id !== null;
    }

    public function ageInDays(): ?int
    {
        return $this->purchase_date?->diffInDays(now());
    }

    /**
     * The most recent maintenance record's `performed_at`, or null.
     */
    public function lastMaintenanceAt(): ?CarbonImmutable
    {
        return $this->maintenanceRecords()
            ->orderByDesc('performed_at')
            ->value('performed_at');
    }

    /**
     * Earliest upcoming `next_due_at` across maintenance records, or null.
     */
    public function nextMaintenanceDueAt(): ?CarbonImmutable
    {
        return $this->maintenanceRecords()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '>=', now())
            ->orderBy('next_due_at')
            ->value('next_due_at');
    }

    public function isMaintenanceOverdue(): bool
    {
        return $this->maintenanceRecords()
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<', now())
            ->exists();
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('tools.is_active', true);
    }

    public function scopeAvailable(Builder $q): Builder
    {
        return $q->where('tools.status', ToolStatus::Available->value);
    }

    public function scopeCheckedOut(Builder $q): Builder
    {
        return $q->where('tools.status', ToolStatus::CheckedOut->value);
    }

    public function scopeUnderMaintenance(Builder $q): Builder
    {
        return $q->where('tools.status', ToolStatus::UnderMaintenance->value);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereHas('checkouts', function (Builder $sub) {
            $sub->whereNull('returned_at')
                ->whereNotNull('expected_return_at')
                ->where('expected_return_at', '<', now());
        });
    }
}
