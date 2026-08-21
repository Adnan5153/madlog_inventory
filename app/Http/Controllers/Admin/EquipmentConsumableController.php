<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EquipmentConsumableStatus;
use App\Enums\EquipmentConsumableType;
use App\Enums\StockMovementType;
use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RecordConsumableEventRequest;
use App\Http\Requests\Admin\StoreEquipmentConsumableRequest;
use App\Http\Requests\Admin\UpdateEquipmentConsumableRequest;
use App\Models\AuditLog;
use App\Models\Battery;
use App\Models\BinLocation;
use App\Models\Equipment;
use App\Models\EquipmentConsumable;
use App\Models\EquipmentConsumableAssignment;
use App\Models\Lubricant;
use App\Models\Part;
use App\Models\Unit;
use App\Models\User;
use App\Services\Inventory\EquipmentConsumableService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentConsumableController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function __construct(
        private readonly EquipmentConsumableService $service,
    ) {
    }

    // ----------------------------------------------------------------
    // Dashboard
    // ----------------------------------------------------------------

    public function dashboard(Request $request): View
    {
        $workshopId = $this->effectiveWorkshopId($request);
        $data = $this->buildDashboardData($workshopId);

        return view('admin.equipment-consumables.dashboard', [
            'title' => 'Equipment consumables',
            'data' => $data,
        ] + $data);
    }

    /**
     * Build the data shape consumed by the dashboard Blade view.
     *
     * Mirrors the Tools dashboard build pattern: real DB queries,
     * workshop-scoped, single grouped query per metric, eager-loaded
     * relationships on every list.
     *
     * @return array<string, mixed>
     */
    private function buildDashboardData(?int $workshopId): array
    {
        $kpis = [
            [
                'title' => 'Active consumables',
                'value' => (int) EquipmentConsumable::query()
                    ->whereHas('currentAssignment')
                    ->count(),
                'meta' => 'Currently tracked against equipment',
                'icon' => 'bi-link-45deg',
                'variant' => 'primary',
                'href' => route('admin.equipment-consumables.index', ['open' => 1]),
            ],
            [
                'title' => 'Total equipment with consumables',
                'value' => (int) EquipmentConsumable::query()
                    ->distinct()
                    ->count('equipment_id'),
                'meta' => 'Distinct pieces of equipment',
                'icon' => 'bi-tools',
                'variant' => 'info',
                'href' => route('admin.equipment-consumables.index'),
            ],
            [
                'title' => 'Consumed this month',
                'value' => (float) EquipmentConsumableAssignment::query()
                    ->where('type', EquipmentConsumableType::Consumed->value)
                    ->where('performed_at', '>=', now()->startOfMonth())
                    ->sum('total_cost'),
                'meta' => 'Cost of consumption (this month)',
                'icon' => 'bi-droplet',
                'variant' => 'warning',
                'format' => 'currency',
                'href' => route('admin.equipment-consumables.report.consumption'),
            ],
            [
                'title' => 'Lifetime cost',
                'value' => (float) EquipmentConsumableAssignment::query()
                    ->where('status', '!=', EquipmentConsumableStatus::Cancelled->value)
                    ->sum('total_cost'),
                'meta' => 'All-time across non-cancelled events',
                'icon' => 'bi-cash-stack',
                'variant' => 'success',
                'format' => 'currency',
                'href' => route('admin.equipment-consumables.index'),
            ],
            [
                'title' => 'Replacement due (30d)',
                'value' => (int) EquipmentConsumable::query()
                    ->whereHas('currentAssignment')
                    ->whereNotNull('expected_replacement_at')
                    ->whereDate('expected_replacement_at', '<=', now()->addDays(30))
                    ->count(),
                'meta' => 'Scheduled or overdue',
                'icon' => 'bi-alarm',
                'variant' => 'danger',
                'href' => route('admin.equipment-consumables.index', ['due' => 1]),
            ],
        ];

        // Status distribution (single grouped query).
        $statusRows = EquipmentConsumableAssignment::query()
            ->whereIn('status', [
                EquipmentConsumableStatus::Assigned->value,
                EquipmentConsumableStatus::Installed->value,
                EquipmentConsumableStatus::Consumed->value,
                EquipmentConsumableStatus::Removed->value,
            ])
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $total = array_sum($statusRows);
        $segments = [];
        foreach ([
            EquipmentConsumableStatus::Assigned,
            EquipmentConsumableStatus::Installed,
            EquipmentConsumableStatus::Consumed,
            EquipmentConsumableStatus::Removed,
        ] as $status) {
            $count = (int) ($statusRows[$status->value] ?? 0);
            $segments[] = [
                'label' => $status->label(),
                'count' => $count,
                'percent' => $total > 0 ? round($count * 100 / $total, 1) : 0,
                'color' => $status->color(),
                'href' => route('admin.equipment-consumables.index', ['status' => $status->value]),
            ];
        }

        // Attention queue.
        $attention = [];

        $overdueReplacements = (int) EquipmentConsumable::query()
            ->whereHas('currentAssignment')
            ->whereNotNull('expected_replacement_at')
            ->whereDate('expected_replacement_at', '<', now())
            ->count();
        if ($overdueReplacements > 0) {
            $attention[] = [
                'label' => 'Overdue replacements',
                'count' => $overdueReplacements,
                'priority' => 'critical',
                'description' => 'Expected replacement date passed',
                'href' => route('admin.equipment-consumables.index', ['overdue' => 1]),
            ];
        }

        $dueSoon = (int) EquipmentConsumable::query()
            ->whereHas('currentAssignment')
            ->whereNotNull('expected_replacement_at')
            ->whereDate('expected_replacement_at', '>=', now())
            ->whereDate('expected_replacement_at', '<=', now()->addDays(7))
            ->count();
        if ($dueSoon > 0) {
            $attention[] = [
                'label' => 'Replacements due this week',
                'count' => $dueSoon,
                'priority' => 'high',
                'description' => 'Within the next 7 days',
                'href' => route('admin.equipment-consumables.index', ['due_soon' => 1]),
            ];
        }

        $consumedThisWeek = (int) EquipmentConsumableAssignment::query()
            ->where('type', EquipmentConsumableType::Consumed->value)
            ->where('performed_at', '>=', now()->subDays(7))
            ->count();
        if ($consumedThisWeek > 0) {
            $attention[] = [
                'label' => 'Consumption events this week',
                'count' => $consumedThisWeek,
                'priority' => 'medium',
                'description' => 'Posted via consume / replace',
                'href' => route('admin.equipment-consumables.report.consumption'),
            ];
        }

        // Top consumed resources (by total quantity consumed in last 90 days).
        // resource_type/resource_id live on equipment_consumables (the parent),
        // not on the assignment row, so we have to join through it.
        $topConsumed = EquipmentConsumableAssignment::query()
            ->join('equipment_consumables', 'equipment_consumables.id', '=', 'equipment_consumable_assignments.equipment_consumable_id')
            ->where('equipment_consumable_assignments.type', EquipmentConsumableType::Consumed->value)
            ->where('equipment_consumable_assignments.performed_at', '>=', now()->subDays(90))
            ->select(
                'equipment_consumables.resource_type',
                'equipment_consumables.resource_id',
                DB::raw('SUM(equipment_consumable_assignments.quantity) as total_qty'),
                DB::raw('SUM(equipment_consumable_assignments.total_cost) as total_cost'),
                DB::raw('COUNT(*) as events'),
            )
            ->groupBy('equipment_consumables.resource_type', 'equipment_consumables.resource_id')
            ->orderByDesc('total_cost')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $resource = $this->resolveResourceInstance($row->resource_type, $row->resource_id);
                return [
                    'name' => $resource ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? 'Resource #'.$row->resource_id) : 'Resource #'.$row->resource_id,
                    'type' => $row->resource_type,
                    'qty' => (float) $row->total_qty,
                    'cost' => (float) $row->total_cost,
                    'events' => (int) $row->events,
                ];
            });

        // Recent activity.
        $recentActivity = EquipmentConsumableAssignment::query()
            ->with([
                'equipmentConsumable.equipment:id,name,asset_number',
                'performedBy:id,name',
            ])
            ->latest('performed_at')
            ->limit(8)
            ->get()
            ->map(function (EquipmentConsumableAssignment $row) {
                $equipment = $row->equipmentConsumable?->equipment;
                return [
                    'event' => $row->type?->label() ?? 'Event',
                    'description' => $equipment ? ($equipment->name.' ('.($equipment->asset_number ?? '#'.$equipment->getKey()).')') : 'Equipment consumable',
                    'subject' => $equipment ? ($equipment->name ?? null) : null,
                    'subjectHref' => $row->equipmentConsumable ? route('admin.equipment-consumables.show', $row->equipmentConsumable) : null,
                    'actor' => $row->performedBy?->name ?? 'System',
                    'timestamp' => $row->performed_at,
                    'icon' => $row->type?->icon() ?? 'bi-activity',
                    'variant' => $row->type?->color() ?? 'neutral',
                ];
            });

        // Recent consumables.
        $recentConsumables = EquipmentConsumable::query()
            ->with([
                'equipment:id,name,asset_number',
                'resource',
                'currentAssignment',
            ])
            ->latest('assigned_at')
            ->limit(6)
            ->get();

        return [
            'kpis' => $kpis,
            'statusSegments' => $segments,
            'statusTotal' => $total,
            'attentionItems' => $attention,
            'topConsumed' => $topConsumed,
            'recentActivity' => $recentActivity,
            'recentConsumables' => $recentConsumables,
            'workshopId' => $workshopId,
        ];
    }

    // ----------------------------------------------------------------
    // Index / search
    // ----------------------------------------------------------------

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $resourceType = $request->query('resource_type');
        $status = $request->query('status');
        $open = $request->boolean('open');

        // The "replacement" select is a single dropdown that maps to
        // three possible filters; legacy `due` / `overdue` / `due_soon`
        // query params still work for deep links.
        $replacement = $request->query('replacement');
        $due = $replacement === 'due' || $request->boolean('due');
        $overdue = $replacement === 'overdue' || $request->boolean('overdue');
        $dueSoon = $replacement === 'due_soon' || $request->boolean('due_soon');

        $consumables = $this->buildIndexQuery($q, $resourceType, $status, $open, $due, $overdue, $dueSoon)
            ->paginate(20)
            ->withQueryString();

        return view('admin.equipment-consumables.index', [
            'title' => 'Equipment consumables',
            'consumables' => $consumables,
            'q' => $q,
            'resourceType' => $resourceType,
            'status' => $status,
            'open' => $open,
            'due' => $due,
            'overdue' => $overdue,
            'dueSoon' => $dueSoon,
            'replacement' => $replacement,
            'resourceTypes' => $this->resourceTypesForFilter(),
            'statuses' => EquipmentConsumableStatus::cases(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $resourceType = $request->query('resource_type');
        $status = $request->query('status');
        $open = $request->boolean('open');
        $replacement = $request->query('replacement');
        $due = $replacement === 'due' || $request->boolean('due');
        $overdue = $replacement === 'overdue' || $request->boolean('overdue');
        $dueSoon = $replacement === 'due_soon' || $request->boolean('due_soon');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.equipment-consumables._row-template',
            singular: 'consumable',
            builder: fn () => $this->buildIndexQuery($q, $resourceType, $status, $open, $due, $overdue, $dueSoon),
        );
    }

    protected function singularNoun(): string
    {
        return 'consumable';
    }

    /**
     * Shared filtered query used by both index() and search().
     */
    private function buildIndexQuery(
        string $q,
        ?string $resourceType,
        ?string $status,
        bool $open,
        bool $due,
        bool $overdue,
        bool $dueSoon,
    ): Builder {
        $allowedResources = EquipmentConsumable::allowedResourceTypes();

        return EquipmentConsumable::query()
            ->with([
                'equipment:id,name,asset_number',
                'resource',
                'currentAssignment.performedBy:id,name',
            ])
            ->withCount('assignments')
            ->when($q !== '', function (Builder $qb) use ($q) {
                $qb->where(function (Builder $w) use ($q) {
                    $w->where('notes', 'like', "%{$q}%")
                        ->orWhereHas('equipment', function (Builder $eq) use ($q) {
                            $eq->where('name', 'like', "%{$q}%")
                                ->orWhere('asset_number', 'like', "%{$q}%");
                        });
                });
            })
            ->when($resourceType && in_array($resourceType, $allowedResources, true), fn (Builder $qb) => $qb->where('resource_type', $resourceType))
            ->when($open, fn (Builder $qb) => $qb->whereHas('currentAssignment'))
            ->when($due, fn (Builder $qb) => $qb->whereNotNull('expected_replacement_at')
                ->whereDate('expected_replacement_at', '<=', now()->addDays(30)))
            ->when($overdue, fn (Builder $qb) => $qb->whereNotNull('expected_replacement_at')
                ->whereDate('expected_replacement_at', '<', now()))
            ->when($dueSoon, fn (Builder $qb) => $qb->whereNotNull('expected_replacement_at')
                ->whereDate('expected_replacement_at', '>=', now())
                ->whereDate('expected_replacement_at', '<=', now()->addDays(7)))
            ->when($status, function (Builder $qb) use ($status) {
                $qb->whereHas('currentAssignment', fn (Builder $sq) => $sq->where('status', $status));
            })
            ->latest('assigned_at');
    }

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------

    public function create(Request $request): View
    {
        $preselectedEquipment = null;
        $equipmentId = $request->query('equipment_id');
        if ($equipmentId !== null) {
            $preselectedEquipment = Equipment::query()->find((int) $equipmentId);
        }

        return view('admin.equipment-consumables.create', [
            'title' => 'Assign consumable',
            'preselectedEquipment' => $preselectedEquipment,
            'equipment' => $this->equipmentList(),
            'parts' => $this->partList(),
            'batteries' => $this->batteryList(),
            'lubricants' => $this->lubricantList(),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'short_code']),
            'bins' => $this->binList(),
            'workshops' => $this->workshopsForForm(),
            'allowedResources' => EquipmentConsumable::allowedResourceTypes(),
        ]);
    }

    public function store(StoreEquipmentConsumableRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $equipment = Equipment::query()->findOrFail($data['equipment_id']);
        $resourceClass = $data['resource_type'];
        $resource = $resourceClass::query()->findOrFail($data['resource_id']);

        $consumable = $this->service->assign(
            equipment: $equipment,
            resource: $resource,
            quantity: (float) $data['quantity'],
            assignedAt: CarbonImmutable::parse($data['assigned_at']),
            expectedReplacementAt: ! empty($data['expected_replacement_at'])
                ? CarbonImmutable::parse($data['expected_replacement_at'])
                : null,
            actor: $request->user(),
            notes: $data['notes'] ?? null,
            binId: $data['bin_id'] ?? null,
            unitId: $data['unit_id'] ?? null,
            unitCost: $data['unit_cost'] ?? null,
        );

        AuditLog::record('equipment-consumable.assigned', $consumable, [
            'equipment_id' => $equipment->id,
            'resource_type' => $resource::class,
            'resource_id' => $resource->getKey(),
            'quantity' => (float) $data['quantity'],
        ]);

        return redirect()
            ->route('admin.equipment-consumables.show', $consumable)
            ->with('status', 'Consumable assigned.');
    }

    public function show(EquipmentConsumable $equipmentConsumable): View
    {
        $equipmentConsumable->load([
            'equipment.department:id,name',
            'resource',
            'assignments.performedBy:id,name',
            'assignments.unit:id,name,short_code',
            'assignments.bin:id,code',
            'currentAssignment',
        ]);

        $replacements = $equipmentConsumable->assignments()
            ->with('previousAssignment')
            ->get();

        return view('admin.equipment-consumables.show', [
            'title' => $equipmentConsumable->equipment?->name ?? 'Consumable',
            'consumable' => $equipmentConsumable,
            'assignments' => $equipmentConsumable->assignments,
            'replacementChain' => $replacements,
            'parts' => $this->partList(),
            'batteries' => $this->batteryList(),
            'lubricants' => $this->lubricantList(),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'short_code']),
            'bins' => $this->binList(),
        ]);
    }

    public function edit(EquipmentConsumable $equipmentConsumable): View
    {
        return view('admin.equipment-consumables.edit', [
            'title' => 'Edit consumable',
            'consumable' => $equipmentConsumable,
        ]);
    }

    public function update(UpdateEquipmentConsumableRequest $request, EquipmentConsumable $equipmentConsumable): RedirectResponse
    {
        $data = $request->validated();

        $equipmentConsumable->fill([
            'expected_replacement_at' => ! empty($data['expected_replacement_at'])
                ? CarbonImmutable::parse($data['expected_replacement_at'])
                : null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()?->getKey(),
        ])->save();

        AuditLog::record('equipment-consumable.updated', $equipmentConsumable, [
            'expected_replacement_at' => $equipmentConsumable->expected_replacement_at?->toDateString(),
        ]);

        return redirect()
            ->route('admin.equipment-consumables.show', $equipmentConsumable)
            ->with('status', 'Consumable updated.');
    }

    public function destroy(EquipmentConsumable $equipmentConsumable): RedirectResponse
    {
        AuditLog::record('equipment-consumable.deleted', $equipmentConsumable, [
            'equipment_id' => $equipmentConsumable->equipment_id,
            'resource_type' => $equipmentConsumable->resource_type,
            'resource_id' => $equipmentConsumable->resource_id,
        ]);

        $equipmentConsumable->delete();

        return redirect()
            ->route('admin.equipment-consumables.index')
            ->with('status', 'Consumable removed.');
    }

    // ----------------------------------------------------------------
    // Action endpoints (verb-driven slide-overs)
    // ----------------------------------------------------------------

    public function consume(RecordConsumableEventRequest $request, EquipmentConsumable $equipmentConsumable): RedirectResponse
    {
        $data = $request->validated();

        $assignment = $this->service->consume(
            consumable: $equipmentConsumable,
            quantity: (float) $data['quantity'],
            at: CarbonImmutable::parse($data['performed_at']),
            actor: $request->user(),
            notes: $data['notes'] ?? null,
            binId: $data['bin_id'] ?? null,
            unitId: $data['unit_id'] ?? null,
            unitCost: $data['unit_cost'] ?? null,
        );

        AuditLog::record('equipment-consumable.consumed', $assignment, [
            'quantity' => (float) $data['quantity'],
            'stock_movement_type' => $assignment->stock_movement_type,
            'stock_movement_id' => $assignment->stock_movement_id,
        ]);

        return redirect()
            ->route('admin.equipment-consumables.show', $equipmentConsumable)
            ->with('status', 'Consumption recorded.');
    }

    public function replace(RecordConsumableEventRequest $request, EquipmentConsumable $equipmentConsumable): RedirectResponse
    {
        $data = $request->validated();

        $newResourceClass = $data['new_resource_type'];
        $newResource = $newResourceClass::query()->findOrFail($data['new_resource_id']);

        $newConsumable = $this->service->replace(
            consumable: $equipmentConsumable,
            newResource: $newResource,
            quantity: (float) $data['quantity'],
            at: CarbonImmutable::parse($data['performed_at']),
            actor: $request->user(),
            notes: $data['notes'] ?? null,
            binId: $data['bin_id'] ?? null,
            unitId: $data['unit_id'] ?? null,
            unitCost: $data['unit_cost'] ?? null,
        );

        AuditLog::record('equipment-consumable.replaced', $newConsumable, [
            'previous_consumable_id' => $equipmentConsumable->getKey(),
            'new_resource_type' => $newResource::class,
            'new_resource_id' => $newResource->getKey(),
        ]);

        return redirect()
            ->route('admin.equipment-consumables.show', $newConsumable)
            ->with('status', 'Consumable replaced.');
    }

    public function remove(RecordConsumableEventRequest $request, EquipmentConsumable $equipmentConsumable): RedirectResponse
    {
        $data = $request->validated();
        $returnQty = (float) ($data['return_quantity'] ?? 0.0);
        $returnToStock = (bool) ($data['return_to_stock'] ?? false) && $returnQty > 0.0;

        $assignment = $this->service->remove(
            consumable: $equipmentConsumable,
            quantity: (float) $data['quantity'],
            at: CarbonImmutable::parse($data['performed_at']),
            actor: $request->user(),
            notes: $data['notes'] ?? null,
            binId: $data['bin_id'] ?? null,
            unitId: $data['unit_id'] ?? null,
            unitCost: $data['unit_cost'] ?? null,
            returnToStockQty: $returnToStock ? $returnQty : 0.0,
        );

        AuditLog::record('equipment-consumable.removed', $assignment, [
            'quantity' => (float) $data['quantity'],
            'returned_to_stock' => $returnToStock,
            'stock_movement_id' => $assignment->stock_movement_id,
        ]);

        return redirect()
            ->route('admin.equipment-consumables.show', $equipmentConsumable)
            ->with('status', 'Consumable removed.');
    }

    // ----------------------------------------------------------------
    // Equipment-anchored sub-routes
    // ----------------------------------------------------------------

    public function forEquipment(Equipment $equipment): View
    {
        $consumables = EquipmentConsumable::query()
            ->with(['resource', 'currentAssignment'])
            ->where('equipment_id', $equipment->id)
            ->latest('assigned_at')
            ->get();

        return view('admin.equipment-consumables.for-equipment', [
            'title' => $equipment->name.' — consumables',
            'equipment' => $equipment,
            'consumables' => $consumables,
        ]);
    }

    // ----------------------------------------------------------------
    // Export + report
    // ----------------------------------------------------------------

    public function export(Request $request): StreamedResponse
    {
        $query = EquipmentConsumable::query()
            ->with(['equipment:id,name,asset_number', 'resource', 'currentAssignment'])
            ->latest('assigned_at')
            ->limit(10000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Equipment', 'Asset #', 'Resource type', 'Resource', 'Status', 'Quantity', 'Total cost', 'Assigned at', 'Expected replacement']);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    $resource = $c->resource;
                    $resourceName = $resource ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? '') : '';
                    fputcsv($out, [
                        $c->equipment?->name ?? '',
                        $c->equipment?->asset_number ?? '',
                        EquipmentConsumable::resourceLabel($c->resource_type),
                        $resourceName,
                        $c->currentAssignment?->status?->label() ?? '',
                        number_format((float) ($c->currentAssignment?->quantity ?? 0), 3),
                        number_format((float) ($c->currentAssignment?->total_cost ?? 0), 4),
                        $c->assigned_at?->toDateTimeString(),
                        $c->expected_replacement_at?->toDateString(),
                    ]);
                }
            });
            fclose($out);
        }, 'equipment-consumables-'.date('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function consumptionReport(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min(365, $days));

        $events = EquipmentConsumableAssignment::query()
            ->with(['equipmentConsumable.equipment:id,name,asset_number', 'equipmentConsumable.resource', 'performedBy:id,name'])
            ->where('type', EquipmentConsumableType::Consumed->value)
            ->where('performed_at', '>=', now()->subDays($days))
            ->orderByDesc('performed_at')
            ->limit(500)
            ->get();

        $totalCost = (float) $events->sum('total_cost');
        $totalQty = (float) $events->sum('quantity');

        return view('admin.equipment-consumables.report', [
            'title' => 'Consumption report',
            'events' => $events,
            'days' => $days,
            'totalCost' => $totalCost,
            'totalQty' => $totalQty,
        ]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Resolve the workshop id the dashboard query should run against.
     * Global admins honour the `?workshop_id=` query string; workshop-
     * scoped admins are locked to their own workshop.
     */
    private function effectiveWorkshopId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        if ($user->isGlobalAdmin()) {
            return $this->selectedWorkshopId($request) ?? $user->workshop_id;
        }
        return $user->workshop_id;
    }

    /**
     * @return array<int, string>
     */
    private function resourceTypesForFilter(): array
    {
        return [
            Part::class => 'Part',
            Battery::class => 'Battery',
            Lubricant::class => 'Lubricant',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Equipment>
     */
    private function equipmentList()
    {
        return Equipment::query()
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'asset_number']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Part>
     */
    private function partList()
    {
        return Part::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'sku']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Battery>
     */
    private function batteryList()
    {
        return Battery::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'battery_code']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Lubricant>
     */
    private function lubricantList()
    {
        return Lubricant::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'lubricant_code']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BinLocation>
     */
    private function binList()
    {
        return BinLocation::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->limit(500)
            ->get(['id', 'code']);
    }

    /**
     * Resolve a single resource instance by its polymorphic type+id.
     * Used by the dashboard's top-consumed list where rows come from a
     * raw join and don't carry an Eloquent relation.
     *
     * Returns null if the resource type isn't one of the known
     * inventory models (Part | Battery | Lubricant).
     */
    private function resolveResourceInstance(string $resourceType, int $resourceId): ?\Illuminate\Database\Eloquent\Model
    {
        if (! in_array($resourceType, \App\Models\EquipmentConsumable::allowedResourceTypes(), true)) {
            return null;
        }
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $resourceType */
        return $resourceType::query()->find($resourceId);
    }
}