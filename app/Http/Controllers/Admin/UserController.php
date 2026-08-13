<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));

        $users = User::query()
            ->with('workshop:id,name', 'rbacRoles:id,name,slug')
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($role !== '', fn ($qb) => $qb->where('role', $role))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'title' => 'Users',
            'users' => $users,
            'q'     => $q,
            'role'  => $role,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', [
            'title'      => 'New user',
            'workshops'  => Workshop::query()->orderBy('name')->get(['id', 'name']),
            'roles'      => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $payload = $request->validate([
            'name'        => ['required', 'string', 'max:160'],
            'email'       => ['required', 'email', 'max:160', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password'    => ['required', 'string', 'min:8', 'confirmed'],
            'role'        => ['required', Rule::in(User::roles())],
            'workshop_id' => ['nullable', 'integer', Rule::exists('workshops', 'id')],
            'rbac_roles'   => ['array'],
            'rbac_roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        $user = User::create([
            'name'      => $payload['name'],
            'email'     => $payload['email'],
            'password'  => Hash::make($payload['password']),
            'role'      => $payload['role'],
            'workshop_id' => $payload['workshop_id'] ?? null,
        ]);

        if (! empty($payload['rbac_roles'])) {
            $user->rbacRoles()->sync($payload['rbac_roles']);
        }

        event(new Registered($user));

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $this->assertSameWorkshop($user);

        return view('admin.users.edit', [
            'title'      => 'Edit user',
            'user'       => $user,
            'workshops'  => Workshop::query()->orderBy('name')->get(['id', 'name']),
            'roles'      => Role::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->assertSameWorkshop($user);
        $this->authorize('update', $user);

        $payload = $request->validate([
            'name'        => ['required', 'string', 'max:160'],
            'email'       => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'password'    => ['nullable', 'string', 'min:8', 'confirmed'],
            'role'        => ['required', Rule::in(User::roles())],
            'workshop_id' => ['nullable', 'integer', Rule::exists('workshops', 'id')],
            'rbac_roles'   => ['array'],
            'rbac_roles.*' => ['integer', Rule::exists('roles', 'id')],
        ]);

        $user->fill([
            'name'        => $payload['name'],
            'email'       => $payload['email'],
            'role'        => $payload['role'],
            'workshop_id' => $payload['workshop_id'] ?? null,
        ]);

        if (! empty($payload['password'])) {
            $user->password = Hash::make($payload['password']);
        }

        $user->save();

        $user->rbacRoles()->sync($payload['rbac_roles'] ?? []);

        return redirect()->route('admin.users.index')->with('status', 'User updated.');
    }

    /**
     * Enforce that the targeted user belongs to the actor's workshop.
     * Global admins always pass; workshop-scoped admins are blocked
     * from editing users in other workshops, regardless of their
     * admin role.
     */
    protected function assertSameWorkshop(User $user): void
    {
        $actor = request()->user();

        if (! $actor) {
            abort(401);
        }

        if ($actor->isGlobalAdmin()) {
            return;
        }

        if ($actor->workshop_id !== $user->workshop_id) {
            abort(403, 'You cannot manage users in another workshop.');
        }
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // No one may delete themselves — even an admin — because that
        // would orphan their own audit history and lock the operator
        // out of the system.
        if ($request->user()?->is($user)) {
            abort(403, 'You cannot delete your own account.');
        }

        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }
}