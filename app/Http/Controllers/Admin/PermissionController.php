<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Services\Access\RolePermissionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only catalogue. Permissions are managed via the role edit page;
 * listing is here so administrators can see the full taxonomy at a
 * glance.
 */
class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected RolePermissionService $rbac) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Permission::class);

        return view('admin.permissions.index', [
            'title' => 'Permissions',
            'grouped' => $this->rbac->permissionsGrouped(),
        ]);
    }

    public function show(Permission $permission): View
    {
        $this->authorize('view', $permission);

        return view('admin.permissions.show', [
            'title' => $permission->name,
            'permission' => $permission,
            'roles' => $permission->roles()->orderBy('name')->get(),
        ]);
    }
}
