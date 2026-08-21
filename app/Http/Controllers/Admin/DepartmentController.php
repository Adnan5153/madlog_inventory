<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $departments = $this->buildDepartmentsQuery($q)
            ->paginate(20)
            ->withQueryString();

        return view('admin.departments.index', [
            'title' => 'Departments',
            'departments' => $departments,
            'q' => $q,
        ]);
    }

    /**
     * Live-search JSON endpoint for the departments index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.departments._row-template',
            singular: 'department',
            builder: fn () => $this->buildDepartmentsQuery($q),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildDepartmentsQuery(string $q)
    {
        return Department::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->withCount('equipment')
            ->with('manager:id,name')
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$departments`.
     */
    protected function singularNoun(): string
    {
        return 'department';
    }

    public function create(): View
    {
        $managers = User::query()->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('admin.departments.create', [
            'title' => 'New department',
            'managers' => $managers,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());
        AuditLog::record('department.created', $department, $department->only(['name', 'code', 'manager_id', 'is_active', 'workshop_id']));

        return redirect()->route('admin.departments.index')->with('status', 'Department created.');
    }

    public function edit(Department $department): View
    {
        $managers = User::query()->orderBy('name')->limit(200)->get(['id', 'name']);

        return view('admin.departments.edit', [
            'title' => 'Edit department',
            'department' => $department,
            'managers' => $managers,
            'workshops' => $this->workshopsForForm(),
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
