<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEquipmentRequest;
use App\Http\Requests\Admin\UpdateEquipmentRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Department;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $equipment = $this->buildEquipmentQuery($q, $status)
            ->paginate(20)
            ->withQueryString();

        return view('admin.equipment.index', [
            'title' => 'Equipment',
            'equipment' => $equipment,
            'q' => $q,
            'status' => $status,
        ]);
    }

    /**
     * Live-search JSON endpoint for the equipment index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.equipment._row-template',
            singular: 'equipment',
            builder: fn () => $this->buildEquipmentQuery($q, $status),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildEquipmentQuery(string $q, ?string $status)
    {
        return Equipment::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('asset_number', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with('department:id,name')
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$equipment`.
     */
    protected function singularNoun(): string
    {
        return 'equipment';
    }

    public function create(): View
    {
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $bins = BinLocation::query()->where('is_active', true)->orderBy('code')->limit(200)->get(['id', 'code']);

        return view('admin.equipment.create', [
            'title' => 'New equipment',
            'departments' => $departments,
            'bins' => $bins,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StoreEquipmentRequest $request): RedirectResponse
    {
        $equipment = Equipment::create($request->validated());
        AuditLog::record('equipment.created', $equipment, $equipment->only(['name', 'asset_number', 'status', 'department_id', 'workshop_id']));

        return redirect()->route('admin.equipment.index')->with('status', 'Equipment created.');
    }

    public function show(Equipment $equipment): View
    {
        $equipment->load('department', 'binLocation');

        return view('admin.equipment.show', [
            'title' => 'Equipment details',
            'equipment' => $equipment,
        ]);
    }

    public function edit(Equipment $equipment): View
    {
        $departments = Department::query()->orderBy('name')->get(['id', 'name']);
        $bins = BinLocation::query()->where('is_active', true)->orderBy('code')->limit(200)->get(['id', 'code']);

        return view('admin.equipment.edit', [
            'title' => 'Edit equipment',
            'equipment' => $equipment,
            'departments' => $departments,
            'bins' => $bins,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function update(UpdateEquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $before = $equipment->only(['name', 'asset_number', 'status', 'department_id', 'bin_location_id', 'is_active']);
        $equipment->update($request->validated());
        AuditLog::record('equipment.updated', $equipment, ['before' => $before, 'after' => $equipment->only(['name', 'asset_number', 'status', 'department_id', 'bin_location_id', 'is_active'])]);

        return redirect()->route('admin.equipment.index')->with('status', 'Equipment updated.');
    }

    public function destroy(Equipment $equipment): RedirectResponse
    {
        AuditLog::record('equipment.deleted', $equipment, $equipment->only(['name', 'asset_number']));
        $equipment->delete();

        return redirect()->route('admin.equipment.index')->with('status', 'Equipment deleted.');
    }
}
