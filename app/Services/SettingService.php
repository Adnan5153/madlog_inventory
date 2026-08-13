<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Reads and writes runtime configuration from the `settings` table
 * with a per-scope in-memory cache.
 *
 * The cache is keyed by workshop scope (so `setting('foo')` for
 * workshop 1 doesn't share a cache with `setting('foo')` for workshop 2)
 * and is invalidated automatically when any row in the table is
 * saved or deleted (see App\Models\Setting::booted()).
 */
class SettingService
{
    /**
     * How long to cache the resolved settings for a single scope.
     * 60s is generous for the read path but short enough that manual
     * config tweaks feel instant in development.
     */
    public const CACHE_TTL_SECONDS = 60;

    /**
     * Read a setting by key. Workshop-scoped values take precedence
     * over global values when a workshop_id is provided.
     *
     * @return mixed The typed value, or $default if not set.
     */
    public function get(string $key, mixed $default = null, ?int $workshopId = null): mixed
    {
        // Cache only the array of typed scalars, not Eloquent models.
        // This sidesteps `__PHP_Incomplete_Class` issues when the
        // database cache driver hydrates a Collection before the model
        // class is autoloaded.
        //
        // We cache BOTH the requested scope and the global scope in a
        // single payload keyed by `scope`. Workshop overrides win over
        // globals; this mirrors how a runtime config lookup typically
        // resolves.
        $payload = Cache::remember(
            $this->cacheKey($workshopId),
            self::CACHE_TTL_SECONDS,
            function () use ($workshopId): array {
                $rows = Setting::query()
                    ->when($workshopId !== null, fn ($q) => $q->where('workshop_id', $workshopId))
                    ->orderBy('group')
                    ->orderBy('key')
                    ->get();

                // For workshop-scoped reads we also need to see globals
                // as a fallback; pull them in the same DB hit when possible.
                // IMPORTANT: globals are merged FIRST (lower precedence);
                // workshop rows override them via the mapWithKeys below.
                $merged = collect();
                if ($workshopId !== null) {
                    $merged = Setting::query()->whereNull('workshop_id')
                        ->orderBy('group')->orderBy('key')
                        ->get();
                }

                return $merged
                    ->mapWithKeys(fn (Setting $s) => [
                        $s->key => [
                            'value'      => $this->castFromRow($s),
                            'type'       => $s->type,
                            'group'      => $s->group,
                            'workshopId' => $s->workshop_id,
                        ],
                    ])
                    ->merge($rows->mapWithKeys(fn (Setting $s) => [
                        $s->key => [
                            'value'      => $this->castFromRow($s),
                            'type'       => $s->type,
                            'group'      => $s->group,
                            'workshopId' => $s->workshop_id,
                        ],
                    ]))
                    ->all();
            }
        );

        $row = $payload[$key] ?? null;
        if ($row === null) {
            return $default;
        }

        return $row['value'];
    }

    /**
     * Persist a setting. If $workshopId is null, the value is global.
     */
    public function set(
        string $key,
        mixed $value,
        ?int $workshopId = null,
        string $group = 'general',
        string $type = 'string',
        ?string $description = null,
    ): Setting {
        $row = Setting::query()
            ->where('key', $key)
            ->where('workshop_id', $workshopId)
            ->first();

        if (! $row) {
            $row = new Setting([
                'key' => $key,
                'workshop_id' => $workshopId,
                'group' => $group,
                'type' => $type,
            ]);
        }

        $row->fill([
            'value' => $value,
            'group' => $group,
            'type' => $type,
            'description' => $description ?? $row->description,
        ])->save();

        return $row;
    }

    /**
     * Forget the cached settings for a single workshop scope (or all
     * scopes when $workshopId is null).
     *
     * Because workshop-scoped payloads embed global rows, any global
     * change must invalidate every workshop payload too. We don't know
     * which workshop IDs are cached, so we increment a generation token
     * and include it in the cache key for workshop-scoped reads. This
     * effectively orphans old workshop caches without enumerating them.
     */
    public function forgetCache(?int $workshopId = null): void
    {
        if ($workshopId === null) {
            // Bump the generation so all workshop-scoped caches become
            // effectively unreadable until they expire.
            $this->bumpGeneration();
            Cache::forget($this->cacheKey(null));
        } else {
            Cache::forget($this->cacheKey($workshopId));
        }
    }

    /**
     * Generate the current generation token, lazily creating it if needed.
     */
    protected function currentGeneration(): int
    {
        return (int) (Cache::get('settings:generation', 0));
    }

    protected function bumpGeneration(): void
    {
        Cache::forever('settings:generation', $this->currentGeneration() + 1);
    }

    /**
     * Return all settings rows for a workshop scope (live DB, not cached).
     * The settings UI uses this directly; runtime code uses get().
     *
     * @return Collection<int, Setting>
     */
    public function all(?int $workshopId = null): Collection
    {
        return Setting::query()
            ->where('workshop_id', $workshopId)
            ->orderBy('group')
            ->orderBy('key')
            ->get();
    }

    /**
     * Return only the settings belonging to one group (e.g. "inventory").
     *
     * @return Collection<int, Setting>
     */
    public function forGroup(string $group, ?int $workshopId = null): Collection
    {
        return $this->all($workshopId)->where('group', $group)->values();
    }

    /**
     * Cast a stored value to a typed scalar matching the row's type.
     */
    protected function castFromRow(Setting $row): mixed
    {
        return match ($row->type) {
            'int' => (int) $row->value,
            'bool' => (bool) $row->value,
            'json' => $row->value,
            default => (string) ($row->value ?? ''),
        };
    }

    protected function cacheKey(?int $workshopId): string
    {
        $gen = $this->currentGeneration();
        return 'settings:'.$gen.':'.($workshopId ?? 'global');
    }
}