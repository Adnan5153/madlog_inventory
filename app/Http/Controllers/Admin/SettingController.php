<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(protected SettingService $settings) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $workshopId = $user?->workshop_id;

        $global = $this->settings->all(null);
        $workshop = $workshopId ? $this->settings->all($workshopId) : null;

        // Group by `group` for the accordion UI.
        $groupGlobal = $global->groupBy('group');
        $groupWorkshop = $workshop?->groupBy('group') ?? collect();

        return view('admin.settings.edit', [
            'title' => 'Settings',
            'groupGlobal' => $groupGlobal,
            'groupWorkshop' => $groupWorkshop,
            'workshopId' => $workshopId,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workshopId = $user?->workshop_id;

        // Payload shape: [key => value, key => value, ...] under the
        // scope hint (global or the user's workshop). Both arrays may
        // be present in the same request.
        $payload = $request->validate([
            'global' => ['nullable', 'array'],
            'workshop' => ['nullable', 'array'],
        ]);

        $updated = 0;
        foreach (['global', 'workshop'] as $scope) {
            $rows = $payload[$scope] ?? [];
            $scopeId = $scope === 'global' ? null : $workshopId;
            if ($scopeId === null && $scope === 'workshop') {
                continue; // workshop-scoped admin can't write workshop overrides
            }

            foreach ($rows as $key => $value) {
                $existing = Setting::query()
                    ->where('key', $key)
                    ->where('workshop_id', $scopeId)
                    ->first();

                if (! $existing) {
                    continue; // don't allow writing unknown keys via the bulk form
                }

                $before = $existing->value;
                $this->settings->set(
                    key: $key,
                    value: $value,
                    workshopId: $scopeId,
                    group: $existing->group,
                    type: $existing->type,
                    description: $existing->description,
                );

                AuditLog::record('setting.updated', $existing, [
                    'key' => $key,
                    'scope' => $scope,
                    'before' => $before,
                    'after' => $value,
                ]);

                $updated++;
            }
        }

        return redirect()->route('admin.settings.edit')
            ->with('status', $updated === 0 ? 'No settings changed.' : "Updated {$updated} setting(s).");
    }
}
