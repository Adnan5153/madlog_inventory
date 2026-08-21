<?php

namespace App\Models;

use Database\Factories\WorkshopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Root tenant entity. Every workshop-scoped row carries workshop_id.
 * Workshop itself is NOT workshop-scoped (it IS the workshop).
 *
 * Soft-deleted so that the cascade on dependent rows (parts, bins,
 * inventory items) never silently destroys historical inventory data.
 * Archived workshops remain queryable for audit and reporting.
 *
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'address', 'phone', 'email', 'is_active'])]
class Workshop extends Model
{
    /** @use HasFactory<WorkshopFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function partCategories(): HasMany
    {
        return $this->hasMany(PartCategory::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function binLocations(): HasMany
    {
        return $this->hasMany(BinLocation::class);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(Tool::class);
    }

    public function toolCheckouts(): HasMany
    {
        return $this->hasMany(ToolCheckout::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Use the slug as the route-model-binding key when applicable.
     */
    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
