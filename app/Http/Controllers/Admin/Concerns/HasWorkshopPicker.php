<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Shared helper for admin controllers that drive a form with a
 * workshop picker.
 *
 * Use the `workshopsForForm()` helper from `create()` and `edit()` so
 * the Blade partial can render a dropdown for global admins and an
 * empty collection for workshop-scoped admins (the form view skips
 * the picker entirely for the latter).
 *
 * Global admins select the workshop via the picker. To keep dependent
 * dropdowns (bins, suppliers, etc.) in sync with what the server will
 * actually accept, the picker reloads the page with `?workshop_id=`
 * on change. Controllers that need to pre-filter related dropdowns
 * for the currently picked workshop read it back via
 * `selectedWorkshopId()`.
 */
trait HasWorkshopPicker
{
    /**
     * Workshop list for the create/edit form.
     *
     * Global admins see every workshop; workshop-scoped admins see an
     * empty collection (the form view won't render the picker anyway).
     */
    protected function workshopsForForm(): Collection
    {
        $user = auth()->user();

        if ($user?->isGlobalAdmin()) {
            return Workshop::query()
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return collect();
    }

    /**
     * Workshop id the global admin has picked via the form's
     * `?workshop_id=` query string.
     *
     * Only meaningful when `workshopsForForm()` returns a non-empty
     * collection (i.e. the user is a global admin). Returns null when
     * no workshop has been picked yet, or when the value is not a
     * positive integer.
     */
    protected function selectedWorkshopId(?Request $request = null): ?int
    {
        $request ??= request();
        $value = $request->query('workshop_id');

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
