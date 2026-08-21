<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreToolMaintenanceRecordRequest;
use App\Http\Requests\Admin\UpdateToolMaintenanceRecordRequest;
use App\Models\AuditLog;
use App\Models\Tool;
use App\Models\ToolMaintenanceRecord;
use App\Models\User;
use App\Scopes\WorkshopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToolMaintenanceController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request, Tool $tool): View
    {
        $q = trim((string) $request->query('q', ''));

        $records = $this->buildRecordsQuery($tool, $q)
            ->paginate(20)
            ->withQueryString();

        return view('admin.tool-maintenance.index', [
            'title' => 'Maintenance · '.$tool->name,
            'tool' => $tool,
            'records' => $records,
            'q' => $q,
        ]);
    }

    public function search(Request $request, Tool $tool): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.tool-maintenance._row-template',
            singular: 'record',
            builder: fn () => $this->buildRecordsQuery($tool, $q),
        );
    }

    protected function singularNoun(): string
    {
        return 'record';
    }

    private function buildRecordsQuery(Tool $tool, string $q): Builder
    {
        return ToolMaintenanceRecord::query()
            ->where('tool_id', $tool->id)
            ->with(['performedBy:id,name'])
            ->when($q !== '', function (Builder $qb) use ($q) {
                $like = '%'.$q.'%';
                $qb->where(function (Builder $w) use ($like) {
                    $w->where('description', 'like', $like)
                        ->orWhere('vendor', 'like', $like)
                        ->orWhere('type', 'like', $like);
                });
            })
            ->orderByDesc('performed_at')
            ->orderBy('id');
    }

    private function usersForForm(?int $workshopId = null)
    {
        if ($workshopId === null) {
            return User::query()->orderBy('name')->get(['id', 'name']);
        }

        return WorkshopScope::disabled(function () use ($workshopId) {
            return User::query()->where('workshop_id', $workshopId)->orderBy('name')->get(['id', 'name']);
        });
    }

    public function create(Request $request, Tool $tool): View
    {
        return view('admin.tool-maintenance.create', [
            'title' => 'Record maintenance · '.$tool->name,
            'tool' => $tool,
            'users' => $this->usersForForm($tool->workshop_id),
        ]);
    }

    public function store(StoreToolMaintenanceRecordRequest $request, Tool $tool): RedirectResponse
    {
        $record = ToolMaintenanceRecord::create(array_merge(
            $request->validated(),
            ['workshop_id' => $tool->workshop_id],
        ));

        AuditLog::record('tool.maintenance_recorded', $tool, [
            'record_id' => $record->id,
            'type' => $record->type->value,
            'vendor' => $record->vendor,
            'cost' => $record->cost,
            'next_due_at' => $record->next_due_at?->toIso8601String(),
        ]);

        return redirect()->route('admin.tool-maintenance.index', $tool)->with('status', 'Maintenance recorded.');
    }

    public function show(Tool $tool, ToolMaintenanceRecord $maintenanceRecord): View
    {
        $maintenanceRecord->load(['performedBy', 'tool']);

        return view('admin.tool-maintenance.show', [
            'title' => 'Maintenance record · '.$tool->name,
            'tool' => $tool,
            'record' => $maintenanceRecord,
        ]);
    }

    public function edit(Tool $tool, ToolMaintenanceRecord $maintenanceRecord): View
    {
        return view('admin.tool-maintenance.edit', [
            'title' => 'Edit maintenance record',
            'tool' => $tool,
            'record' => $maintenanceRecord,
            'users' => $this->usersForForm($tool->workshop_id),
        ]);
    }

    public function update(UpdateToolMaintenanceRecordRequest $request, Tool $tool, ToolMaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        $before = $maintenanceRecord->only(['type', 'description', 'cost', 'next_due_at']);
        $maintenanceRecord->update($request->validated());
        AuditLog::record('tool.maintenance_updated', $tool, [
            'record_id' => $maintenanceRecord->id,
            'before' => $before,
            'after' => $maintenanceRecord->only(['type', 'description', 'cost', 'next_due_at']),
        ]);

        return redirect()->route('admin.tool-maintenance.index', $tool)->with('status', 'Maintenance record updated.');
    }

    public function destroy(Tool $tool, ToolMaintenanceRecord $maintenanceRecord): RedirectResponse
    {
        if ($maintenanceRecord->next_due_at !== null && $maintenanceRecord->next_due_at->isPast()) {
            return back()->withErrors([
                'record' => 'Cannot delete a record whose next_due_at has already passed (it represents fulfilled history).',
            ]);
        }

        AuditLog::record('tool.maintenance_deleted', $tool, [
            'record_id' => $maintenanceRecord->id,
            'type' => $maintenanceRecord->type->value,
        ]);
        $maintenanceRecord->delete();

        return redirect()->route('admin.tool-maintenance.index', $tool)->with('status', 'Maintenance record deleted.');
    }
}
