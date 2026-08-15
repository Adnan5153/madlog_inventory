<?php

use App\Models\User;
use App\Services\SettingService;

if (! function_exists('setting')) {
    /**
     * Read a runtime configuration value from the `settings` table.
     *
     * Reads are cached per workshop scope and invalidated automatically
     * when any Setting row changes (see App\Models\Setting::booted()).
     *
     * @param  mixed  $default  Returned when the key is not set.
     * @param  int|null  $workshopId  When provided, looks up a
     *                                workshop-scoped override; falls
     *                                back to the global value if no
     *                                workshop-scoped row exists.
     */
    function setting(string $key, mixed $default = null, ?int $workshopId = null): mixed
    {
        /** @var SettingService $service */
        $service = app(SettingService::class);

        // Caller didn't specify a workshop; use the current user's
        // workshop (or null = global when unauthenticated).
        if (func_num_args() < 3) {
            $workshopId = auth()->user()?->workshop_id;
        }

        return $service->get($key, $default, $workshopId);
    }
}
