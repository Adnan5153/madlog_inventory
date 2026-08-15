<?php

namespace App\Models;

use App\Services\SettingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Database-driven configuration values.
 *
 * Each setting row has a `key` (e.g. "inventory.allow_negative_stock"),
 * a `group` (categories for the settings UI), a `type` (string|int|bool|json
 * — drives casting on read) and an optional `workshop_id` (null = global).
 *
 * Reads go through App\Services\SettingService with a cache layer; writes
 * invalidates the cache automatically via model events.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property int|null $workshop_id
 * @property string $group
 * @property string $type
 * @property string|null $description
 */
#[Fillable(['key', 'value', 'workshop_id', 'group', 'type', 'description'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * Invalidate the settings cache whenever a row is mutated.
     * SettingService is responsible for actually clearing it.
     */
    protected static function booted(): void
    {
        $flush = function (Setting $setting): void {
            try {
                app(SettingService::class)->forgetCache($setting->workshop_id);
            } catch (\Throwable) {
                // Service not yet bound (e.g. during migrate); ignore.
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->whereNull('workshop_id');
    }

    public function scopeForWorkshop(Builder $query, int $workshopId): Builder
    {
        return $query->where('workshop_id', $workshopId);
    }

    /**
     * Cast the stored JSON value back to a typed scalar matching `type`.
     */
    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => (bool) $this->value,
            'json' => $this->value,
            default => (string) ($this->value ?? ''),
        };
    }
}
