<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Role;
use App\Services\Access\RolePermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    use AuthorizesRequests;
    public function __construct(protected RolePermissionService $rbac)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'title' => 'Roles',
            'roles' => $roles,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Role::class);

        return view('admin.roles.create', [
            'title'        => 'New role',
            'grouped'      => $this->rbac->permissionsGrouped(),
            'rolePermIds'  => [],
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
                'is_system'   => false,
            ]);

            $this->rbac->syncRolePermissions($role, $data['permissions'] ?? []);

            return $role;
        });

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function show(Role $role): View
    {
        $this->authorize('view', $role);

        $role->load(['permissions']);

        return view('admin.roles.show', [
            'title'   => $role->name,
            'role'    => $role,
            'grouped' => $this->rbac->permissionsGrouped(),
        ]);
    }

    public function edit(Role $role): View
    {
        $this->authorize('update', $role);

        return view('admin.roles.edit', [
            'title'       => 'Edit role',
            'role'        => $role,
            'grouped'     => $this->rbac->permissionsGrouped(),
            'rolePermIds' => $role->permissions()->pluck('permissions.id')->all(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorize('update', $role);

        $data = $request->validated();

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name'        => $data['name'],
                'slug'        => $data['slug'],
                'description' => $data['description'] ?? null,
            ]);

            $this->rbac->syncRolePermissions($role, $data['permissions'] ?? []);
        });

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        // System roles cannot be deleted regardless of the actor's
        // permissions. The Gate::before super-admin bypass would
        // otherwise let an admin blow away a built-in role, so we
        // enforce this in the controller as a backstop.
        if ($role->is_system) {
            abort(403, 'Built-in roles cannot be deleted.');
        }

        $this->authorize('delete', $role);

        DB::transaction(function () use ($role) {
            $role->users()->detach();
            $role->permissions()->detach();
            $role->delete();
        });

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }
}