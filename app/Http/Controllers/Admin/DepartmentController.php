<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $departments = Department::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->withCount('equipment')
            ->with('manager:id,name')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.departments.index', [
            'title' => 'Departments',
            'departments' => $departments,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        $managers = User::query()->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('admin.departments.create', [
            'title' => 'New department',
            'managers' => $managers,
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());
        AuditLog::record('department.created', $department, $department->only(['name', 'code', 'manager_id', 'is_active']));

        return redirect()->route('admin.departments.index')->with('status', 'Department created.');
    }

    public function edit(Department $department): View
    {
        $managers = User::query()->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('admin.departments.edit', [
            'title' => 'Edit department',
            'department' => $department,
            'managers' => $managers,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $before = $department->only(['name', 'code', 'manager_id', 'is_active']);
        $department->update($request->validated());
        AuditLog::record('department.updated', $department, ['before' => $before, 'after' => $department->only(['name', 'code', 'manager_id', 'is_active'])]);

        return redirect()->route('admin.departments.index')->with('status', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->equipment()->exists()) {
            return back()->withErrors(['department' => 'Cannot delete a department that still has equipment.']);
        }

        AuditLog::record('department.deleted', $department, $department->only(['name', 'code']));
        $department->delete();

        return redirect()->route('admin.departments.index')->with('status', 'Department deleted.');
    }
}
