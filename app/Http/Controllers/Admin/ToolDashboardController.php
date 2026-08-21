<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ToolMaintenanceType;
use App\Enums\ToolStatus;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\ToolMaintenanceRecord;
use App\Models\User;
use App\Services\Inventory\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ToolDashboardController extends Controller
{
    use HasWorkshopPicker;

    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): View
    {
        $workshopId = $this->selectedWorkshopId($request) ?? auth()->user()?->workshop_id;

        $data = $this->buildDashboardData($workshopId);

        return view('admin.tools.dashboard', array_merge(
            ['title' => 'Tools dashboard'],
            $data,
        ));
    }

    /**
     * Build every dataset the dashboard renders. Each block returns at
     * most 5–10 rows so the page is scannable; every list has a "View
     * all" link to a real filtered index page. All queries go through
     * the Eloquent global scope (`BelongsToWorkshop`) so workshop
     * isolation is enforced at the framework level — we only add an
     * explicit `workshop_id` filter where the global scope doesn't
     * reach (audit logs, cross-table joins).
     *
     * @return array<string, mixed>
     */
    private function buildDashboardData(?int $workshopId): array
    {
        $now = Carbon::now();

        // ------------------------------------------------------------------
        // 1. Executive KPIs — single grouped query, derived metrics in PHP
        // ------------------------------------------------------------------
        $baseTools = Tool::query();
        if ($workshopId !== null) {
            $baseTools->where('tools.workshop_id', $workshopId);
        }

        $countByStatus = (clone $baseTools)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $total = array_sum($countByStatus);

        $active = (clone $baseTools)->where('tools.is_active', true)->count();

        $maintenanceDue = (clone $baseTools)
            ->whereHas('maintenanceRecords', fn (Builder $q) => $q
                ->whereNotNull('next_due_at')
                ->where('next_due_at', '<', $now))
            ->count();

        $overdueCheckouts = ToolCheckout::query()
            ->whereNull('returned_at')
            ->whereNotNull('expected_return_at')
            ->where('expected_return_at', '<', $now)
            ->when($workshopId !== null, fn ($q) => $q->where('tool_checkouts.workshop_id', $workshopId))
            ->count();

        $kpis = [
            'total' => $total,
            'active' => $active,
            'available' => $countByStatus[ToolStatus::Available->value] ?? 0,
            'checked_out' => $countByStatus[ToolStatus::CheckedOut->value] ?? 0,
            'under_maintenance' => $countByStatus[ToolStatus::UnderMaintenance->value] ?? 0,
            'out_of_service' => $countByStatus[ToolStatus::OutOfService->value] ?? 0,
            'damaged' => $countByStatus[ToolStatus::Damaged->value] ?? 0,
            'lost' => $countByStatus[ToolStatus::Lost->value] ?? 0,
            'retired' => $countByStatus[ToolStatus::Retired->value] ?? 0,
            'maintenance_due' => $maintenanceDue,
            'overdue_checkouts' => $overdueCheckouts,
        ];

        $kpis['requires_attention'] = $maintenanceDue
            + ($kpis['damaged'] ?? 0)
            + ($kpis['lost'] ?? 0)
            + $overdueCheckouts;

        // ------------------------------------------------------------------
        // 2. Operational health — derived from the same status map
        // ------------------------------------------------------------------
        $pct = static fn (int $n): int => $total > 0 ? (int) round($n / $total * 100) : 0;

        $opHealth = [
            ['label' => 'Available',   'count' => $kpis['available'],          'percent' => $pct($kpis['available']),          'variant' => 'success'],
            ['label' => 'In Use',      'count' => $kpis['checked_out'],        'percent' => $pct($kpis['checked_out']),        'variant' => 'primary'],
            ['label' => 'Maintenance', 'count' => $kpis['under_maintenance'],  'percent' => $pct($kpis['under_maintenance']),  'variant' => 'warning'],
            ['label' => 'Out of Service', 'count' => $kpis['out_of_service'] + $kpis['damaged'] + $kpis['lost'], 'percent' => $pct($kpis['out_of_service'] + $kpis['damaged'] + $kpis['lost']), 'variant' => 'danger'],
        ];

        // ------------------------------------------------------------------
        // 3. Attention center — 4 actionable items
        // ------------------------------------------------------------------
        $attentionItems = [
            [
                'label' => 'Maintenance overdue',
                'count' => $maintenanceDue,
                'priority' => 'high',
                'description' => 'Tools whose scheduled maintenance date has passed.',
                'href' => route('admin.tools.index', ['status' => ToolStatus::UnderMaintenance->value]),
            ],
            [
                'label' => 'Damaged tools',
                'count' => $kpis['damaged'],
                'priority' => 'high',
                'description' => 'Tools flagged with damaged condition or status.',
                'href' => route('admin.tools.index', ['status' => ToolStatus::Damaged->value]),
            ],
            [
                'label' => 'Lost tools',
                'count' => $kpis['lost'],
                'priority' => 'critical',
                'description' => 'Tools missing from inventory — review immediately.',
                'href' => route('admin.tools.index', ['status' => ToolStatus::Lost->value]),
            ],
            [
                'label' => 'Overdue checkouts',
                'count' => $overdueCheckouts,
                'priority' => 'high',
                'description' => 'Open checkouts past their expected return date.',
                'href' => route('admin.tools.index', ['status' => ToolStatus::CheckedOut->value]),
            ],
        ];

        // ------------------------------------------------------------------
        // 4. Tool status distribution — reuses the grouped counts above
        // ------------------------------------------------------------------
        $statusSegments = [
            ['key' => ToolStatus::Available->value,        'label' => 'Available',         'count' => $kpis['available'],         'color' => 'success', 'href' => route('admin.tools.index', ['status' => ToolStatus::Available->value])],
            ['key' => ToolStatus::CheckedOut->value,       'label' => 'Checked out',       'count' => $kpis['checked_out'],       'color' => 'primary', 'href' => route('admin.tools.index', ['status' => ToolStatus::CheckedOut->value])],
            ['key' => ToolStatus::UnderMaintenance->value, 'label' => 'Under maintenance', 'count' => $kpis['under_maintenance'], 'color' => 'warning', 'href' => route('admin.tools.index', ['status' => ToolStatus::UnderMaintenance->value])],
            ['key' => ToolStatus::OutOfService->value,     'label' => 'Out of service',    'count' => $kpis['out_of_service'],    'color' => 'secondary', 'href' => route('admin.tools.index', ['status' => ToolStatus::OutOfService->value])],
            ['key' => ToolStatus::Damaged->value,          'label' => 'Damaged',           'count' => $kpis['damaged'],           'color' => 'danger',  'href' => route('admin.tools.index', ['status' => ToolStatus::Damaged->value])],
            ['key' => ToolStatus::Lost->value,             'label' => 'Lost',              'count' => $kpis['lost'],              'color' => 'danger',  'href' => route('admin.tools.index', ['status' => ToolStatus::Lost->value])],
            ['key' => ToolStatus::Retired->value,          'label' => 'Retired',           'count' => $kpis['retired'],           'color' => 'neutral', 'href' => route('admin.tools.index', ['status' => ToolStatus::Retired->value])],
        ];

        // ------------------------------------------------------------------
        // 5. Assignment overview — top 10 holders by open-checkout count
        // ------------------------------------------------------------------
        $assignmentRows = ToolCheckout::query()
            ->selectRaw('user_id, COUNT(*) AS total,
                SUM(CASE WHEN expected_return_at IS NOT NULL AND expected_return_at < ? THEN 1 ELSE 0 END) AS overdue',
                [$now])
            ->whereNull('returned_at')
            ->when($workshopId !== null, fn ($q) => $q->where('tool_checkouts.workshop_id', $workshopId))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $usersById = User::query()
            ->whereIn('id', $assignmentRows->pluck('user_id')->all())
            ->get(['id', 'name'])
            ->keyBy('id');

        $assignments = $assignmentRows->map(fn ($row) => [
            'user' => $usersById[$row->user_id] ?? null,
            'total' => (int) $row->total,
            'overdue' => (int) $row->overdue,
        ])->all();

        // ------------------------------------------------------------------
        // 6. Currently checked-out tools — top 10 open checkouts
        // ------------------------------------------------------------------
        $checkedOut = ToolCheckout::query()
            ->with(['tool:id,name,tool_code,category_id', 'tool.category:id,name', 'user:id,name'])
            ->whereNull('returned_at')
            ->when($workshopId !== null, fn ($q) => $q->where('tool_checkouts.workshop_id', $workshopId))
            ->orderBy('expected_return_at')
            ->limit(10)
            ->get();

        // ------------------------------------------------------------------
        // 7. Maintenance schedule — overdue / due this week / upcoming
        // ------------------------------------------------------------------
        $maintenanceRows = ToolMaintenanceRecord::query()
            ->with(['tool:id,name,tool_code'])
            ->whereNotNull('next_due_at')
            ->when($workshopId !== null, fn ($q) => $q->where('tool_maintenance_records.workshop_id', $workshopId))
            ->orderBy('next_due_at')
            ->limit(100)
            ->get();

        $maintenanceOverdue = $maintenanceRows->filter(fn ($r) => $r->next_due_at !== null && $r->next_due_at->lt($now))->take(10)->values();
        $maintenanceDueThisWeek = $maintenanceRows->filter(
            fn ($r) => $r->next_due_at !== null && $r->next_due_at->between($now, $now->copy()->addDays(7))
        )->take(10)->values();
        $maintenanceUpcoming = $maintenanceRows->filter(
            fn ($r) => $r->next_due_at !== null && $r->next_due_at->gt($now->copy()->addDays(7))
        )->take(5)->values();

        // ------------------------------------------------------------------
        // 8. Inspection / calibration status — derived from inspection rows
        // ------------------------------------------------------------------
        $inspectionBase = ToolMaintenanceRecord::query()
            ->with(['tool:id,name,tool_code'])
            ->where('type', ToolMaintenanceType::Inspection->value)
            ->when($workshopId !== null, fn ($q) => $q->where('tool_maintenance_records.workshop_id', $workshopId));

        $inspectionRows = (clone $inspectionBase)
            ->whereNotNull('next_due_at')
            ->orderBy('next_due_at')
            ->limit(100)
            ->get();

        $inspectionBuckets = [
            'overdue' => [
                'count' => $inspectionRows->filter(fn ($r) => $r->next_due_at->lt($now))->count(),
                'items' => $inspectionRows->filter(fn ($r) => $r->next_due_at->lt($now))->take(5)->values(),
            ],
            'due_soon' => [
                'count' => $inspectionRows->filter(fn ($r) => $r->next_due_at->between($now, $now->copy()->addDays(14)))->count(),
            ],
            'upcoming' => [
                'count' => $inspectionRows->filter(fn ($r) => $r->next_due_at->gt($now->copy()->addDays(14)))->count(),
            ],
            'recently_passed' => [
                'count' => (clone $inspectionBase)
                    ->where('performed_at', '>=', $now->copy()->subDays(30))
                    ->count(),
            ],
        ];

        // ------------------------------------------------------------------
        // 9. Recent activity — top 15 tool-related audit logs
        // ------------------------------------------------------------------
        $recentActivity = AuditLog::query()
            ->with(['user:id,name'])
            ->when($workshopId !== null, fn ($q) => $q->where('audit_logs.workshop_id', $workshopId))
            ->where(function (Builder $q) {
                $q->where('subject_type', Tool::class)
                    ->orWhere('subject_type', ToolMaintenanceRecord::class)
                    ->orWhere('subject_type', \App\Models\ToolCategory::class)
                    ->orWhere('subject_type', ToolCheckout::class)
                    ->orWhere('action', 'like', 'tool.%')
                    ->orWhere('action', 'like', 'tool_%');
            })
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        // ------------------------------------------------------------------
        // 10. Recently added tools — top 5
        // ------------------------------------------------------------------
        $recentTools = Tool::query()
            ->with(['category:id,name', 'binLocation:id,code'])
            ->when($workshopId !== null, fn ($q) => $q->where('tools.workshop_id', $workshopId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // ------------------------------------------------------------------
        // 11. High-value tools — top 5 by purchase_price
        // ------------------------------------------------------------------
        $highValueTools = Tool::query()
            ->with(['category:id,name'])
            ->whereNotNull('purchase_price')
            ->when($workshopId !== null, fn ($q) => $q->where('tools.workshop_id', $workshopId))
            ->orderByDesc('purchase_price')
            ->limit(5)
            ->get();

        // ------------------------------------------------------------------
        // Aggregate & return
        // ------------------------------------------------------------------
        return [
            'kpis' => $kpis,
            'opHealth' => $opHealth,
            'attentionItems' => $attentionItems,
            'statusSegments' => $statusSegments,
            'assignments' => $assignments,
            'checkedOut' => $checkedOut,
            'maintenanceOverdue' => $maintenanceOverdue,
            'maintenanceDueThisWeek' => $maintenanceDueThisWeek,
            'maintenanceUpcoming' => $maintenanceUpcoming,
            'inspectionBuckets' => $inspectionBuckets,
            'recentActivity' => $recentActivity,
            'recentTools' => $recentTools,
            'highValueTools' => $highValueTools,
            'workshopId' => $workshopId,
        ];
    }
}