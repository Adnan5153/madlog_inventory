<?php

namespace App\Http\Requests\Concerns;

/**
 * Shared workshop-scoping logic for store/update FormRequests that
 * write to workshop-scoped tables.
 *
 * The pattern is:
 *
 *  - Workshop-scoped admins never see a workshop picker. We force
 *    `workshop_id` to their own workshop in `prepareForValidation()`,
 *    so the form view stays simple and a crafted payload can't sneak
 *    a different workshop through.
 *
 *  - Global admins (role=admin with no workshop) must pick a workshop
 *    explicitly. The form view renders the picker only for them.
 *
 *  - The route-bound model's `workshop_id` is authoritative on update:
 *    we never let a global admin move a record to a different workshop
 *    by re-saving it. Any incoming `workshop_id` is dropped.
 */
trait HandlesWorkshopScoping
{
    /**
     * The workshop id the record will belong to.
     *
     * For store requests this is the user's workshop (workshop-scoped
     * admins) or the form input (global admins). For update requests
     * `lockWorkshopFromRouteModel()` will have already injected the
     * model's existing workshop into the payload, so this is always
     * the correct value.
     */
    public function effectiveWorkshopId(): int
    {
        return (int) ($this->input('workshop_id') ?? 0);
    }

    /**
     * Standard `workshop_id` validation rule for store/update requests.
     *
     * Always require an integer that exists in the workshops table —
     * by the time validation runs, `prepareForWorkshopScoping()` (or
     * `lockWorkshopFromRouteModel()` for updates) has injected the
     * correct value, so we just need to confirm it's well-formed.
     */
    protected function workshopRule(): array
    {
        return ['required', 'integer', 'exists:workshops,id'];
    }

    /**
     * Inject `workshop_id` for workshop-scoped admins before validation.
     *
     * Call this from `prepareForValidation()` in store requests.
     * Global admins pass through untouched so the form-supplied value
     * is validated.
     */
    protected function prepareForWorkshopScoping(): void
    {
        $user = $this->user();
        if ($user && ! $user->isGlobalAdmin() && $user->workshop_id !== null) {
            $this->merge(['workshop_id' => $user->workshop_id]);
        }
    }

    /**
     * Lock `workshop_id` to the route-bound model's existing workshop.
     *
     * Call this from `prepareForValidation()` in update requests. We
     * never want a global admin to be able to move a record to a
     * different workshop by re-saving it through the edit form, so
     * any incoming `workshop_id` is dropped and replaced with the
     * model's current value.
     *
     * @param  string  $routeParam  The route param name (e.g. 'brand',
     *                              'product', 'category').
     */
    protected function lockWorkshopFromRouteModel(string $routeParam): void
    {
        $model = $this->route($routeParam);
        $workshopId = $model?->getAttribute('workshop_id');
        if ($workshopId === null) {
            return;
        }

        // Replace any client-supplied value with the model's own.
        $this->offsetUnset('workshop_id');
        $this->merge(['workshop_id' => $workshopId]);
    }
}
