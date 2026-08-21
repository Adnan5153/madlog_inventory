@php
    use App\Enums\ToolMaintenanceType;
    use App\Enums\ToolStatus;

    /**
     * Human label + icon + variant for a tool-related audit-log action.
     * Anything outside this map falls back to a neutral "Other".
     *
     * @return array{label:string,icon:string,variant:string}
     */
    $describeAction = static function (string $action): array {
        return match ($action) {
            'tool.created'               => ['label' => 'Tool registered',        'icon' => 'bi-plus-circle',        'variant' => 'success'],
            'tool.updated'               => ['label' => 'Tool updated',           'icon' => 'bi-pencil',             'variant' => 'info'],
            'tool.deleted'               => ['label' => 'Tool deleted',           'icon' => 'bi-trash',              'variant' => 'danger'],
            'tool.maintenance_recorded'  => ['label' => 'Maintenance recorded',   'icon' => 'bi-wrench',             'variant' => 'warning'],
            'tool.maintenance_updated'   => ['label' => 'Maintenance updated',    'icon' => 'bi-wrench-adjustable',  'variant' => 'info'],
            'tool.maintenance_deleted'   => ['label' => 'Maintenance deleted',    'icon' => 'bi-wrench',             'variant' => 'danger'],
            default                      => ['label' => str_replace(['.', '_'], ' ', $action), 'icon' => 'bi-activity', 'variant' => 'neutral'],
        };
    };

    /** Resolve the friendly subject for an audit log entry. */
    $describeSubject = static function ($log) {
        $subject = $log->subject;
        if ($subject instanceof \App\Models\Tool) {
            return ['name' => $subject->name, 'href' => route('admin.tools.show', $subject)];
        }
        if ($subject instanceof \App\Models\ToolMaintenanceRecord) {
            $tool = $subject->tool;
            return [
                'name' => $tool?->name ?? 'Maintenance record',
                'href' => $tool ? route('admin.tool-maintenance.index', $tool) : null,
            ];
        }
        if ($subject instanceof \App\Models\ToolCategory) {
            return ['name' => $subject->name, 'href' => route('admin.tool-categories.show', $subject)];
        }
        return ['name' => null, 'href' => null];
    };
@endphp

@extends('layouts.admin', ['title' => $title ?? 'Tools dashboard'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => 'Dashboard'],
    ]" />

    <x-admin.page-header title="Tools overview" subtitle="Monitor tool availability, assignments, maintenance, inspections and operational status from one place.">
        <x-slot:actions>
            <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list me-1"></i> All tools
            </a>
            @can('create', App\Models\Tool::class)
                <a href="{{ route('admin.tools.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add tool
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-dashboard">

        {{-- ============================================================
             KPI ROW
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-sm-6 col-xl">
                <x-admin.dashboard.kpi-card
                    title="Total tools"
                    :value="$kpis['total']"
                    meta="All tracked tool assets"
                    icon="bi-tools"
                    variant="primary"
                    :href="route('admin.tools.index')" />
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <x-admin.dashboard.kpi-card
                    title="Available"
                    :value="$kpis['available']"
                    meta="Ready for operational use"
                    icon="bi-check2-circle"
                    variant="success"
                    :href="route('admin.tools.index', ['status' => ToolStatus::Available->value])" />
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <x-admin.dashboard.kpi-card
                    title="Checked out"
                    :value="$kpis['checked_out']"
                    :meta="$kpis['overdue_checkouts'] > 0 ? $kpis['overdue_checkouts'].' overdue' : 'Currently in use'"
                    icon="bi-box-arrow-up-right"
                    variant="info"
                    :href="route('admin.tools.index', ['status' => ToolStatus::CheckedOut->value])" />
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <x-admin.dashboard.kpi-card
                    title="Maintenance"
                    :value="$kpis['under_maintenance']"
                    :meta="$kpis['maintenance_due'] > 0 ? $kpis['maintenance_due'].' overdue' : 'Scheduled or in progress'"
                    icon="bi-wrench"
                    variant="warning"
                    :href="route('admin.tools.index', ['status' => ToolStatus::UnderMaintenance->value])" />
            </div>
            <div class="col-12 col-sm-6 col-xl">
                <x-admin.dashboard.kpi-card
                    title="Attention required"
                    :value="$kpis['requires_attention']"
                    meta="Damaged, lost, overdue maintenance or checkouts"
                    icon="bi-exclamation-triangle"
                    variant="danger" />
            </div>
        </div>

        {{-- ============================================================
             ROW A: Operational Health + Attention Center
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-7">
                <section class="admin-card" aria-labelledby="op-health-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="op-health-heading">Operational health</h2>
                            <p class="dashboard-section-subtitle">Distribution of the {{ $kpis['total'] }} tools in this workshop.</p>
                        </div>
                    </header>

                    <x-admin.dashboard.status-segmented-bar
                        :segments="collect($opHealth)->map(fn ($row) => [
                            'label'   => $row['label'],
                            'count'   => $row['count'],
                            'percent' => $row['percent'],
                            'color'   => $row['variant'],
                        ])->all()"
                        :total="$kpis['total']" />
                </section>
            </div>

            <div class="col-12 col-lg-5">
                <section class="admin-card" aria-labelledby="attention-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="attention-heading">Requires attention</h2>
                            <p class="dashboard-section-subtitle">Operational exceptions that need follow-up.</p>
                        </div>
                    </header>

                    <div class="attention-list">
                        @foreach($attentionItems as $item)
                            <x-admin.dashboard.attention-item
                                :label="$item['label']"
                                :count="$item['count']"
                                :priority="$item['priority']"
                                :description="$item['description']"
                                :href="$item['href']" />
                        @endforeach
                    </div>
                </section>
            </div>
        </div>

        {{-- ============================================================
             ROW B: Status Distribution + Quick Actions
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-8">
                <section class="admin-card" aria-labelledby="status-dist-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="status-dist-heading">Tool status distribution</h2>
                            <p class="dashboard-section-subtitle">Click a status to filter the tools list.</p>
                        </div>
                        <div class="dashboard-section-actions">
                            <a href="{{ route('admin.tools.index') }}" class="btn btn-sm btn-outline-secondary">View all tools</a>
                        </div>
                    </header>

                    <x-admin.dashboard.status-segmented-bar
                        :segments="collect($statusSegments)->map(fn ($s) => [
                            'label'   => $s['label'],
                            'count'   => $s['count'],
                            'percent' => $kpis['total'] > 0 ? (int) round($s['count'] / $kpis['total'] * 100) : 0,
                            'color'   => $s['color'],
                            'href'    => $s['href'],
                        ])->all()"
                        :total="$kpis['total']" />
                </section>
            </div>

            <div class="col-12 col-lg-4">
                <section class="admin-card" aria-labelledby="quick-actions-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="quick-actions-heading">Quick actions</h2>
                            <p class="dashboard-section-subtitle">Jump to common workflows.</p>
                        </div>
                    </header>

                    <nav class="quick-actions" aria-label="Tool quick actions">
                        @can('create', App\Models\Tool::class)
                            <a class="quick-actions__item" href="{{ route('admin.tools.create') }}">
                                <i class="bi bi-plus-circle" aria-hidden="true"></i> Add tool
                            </a>
                        @endcan
                        <a class="quick-actions__item" href="{{ route('admin.tool-categories.index') }}">
                            <i class="bi bi-tags" aria-hidden="true"></i> Manage categories
                        </a>
                        <a class="quick-actions__item" href="{{ route('admin.tools.index', ['status' => ToolStatus::CheckedOut->value]) }}">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Checked-out tools
                        </a>
                        <a class="quick-actions__item" href="{{ route('admin.tools.index', ['status' => ToolStatus::Available->value]) }}">
                            <i class="bi bi-box-arrow-in-down-left" aria-hidden="true"></i> Check-in (available)
                        </a>
                        <a class="quick-actions__item" href="{{ route('admin.tools.index') }}">
                            <i class="bi bi-wrench" aria-hidden="true"></i> Maintenance
                        </a>
                        <a class="quick-actions__item" href="{{ route('admin.audit-logs.index') }}">
                            <i class="bi bi-shield-check" aria-hidden="true"></i> Audit log
                        </a>
                    </nav>
                </section>
            </div>
        </div>

        {{-- ============================================================
             ROW C: Assignment Overview + Currently Checked-Out
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-5">
                <section class="admin-card" aria-labelledby="assignments-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="assignments-heading">Currently assigned tools</h2>
                            <p class="dashboard-section-subtitle">Top holders with the most open checkouts.</p>
                        </div>
                    </header>

                    @if(empty($assignments))
                        <x-admin.empty-state icon="bi-people" title="No tools currently checked out">
                            Once a tool is checked out it will appear here.
                        </x-admin.empty-state>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Holder</th>
                                        <th class="num">Tools</th>
                                        <th class="num">Overdue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($assignments as $row)
                                        <tr>
                                            <td>
                                                {{ $row['user']?->name ?? '—' }}
                                                <div class="text-muted small">User #{{ $row['user']?->id ?? '—' }}</div>
                                            </td>
                                            <td class="num">{{ $row['total'] }}</td>
                                            <td class="num">
                                                @if($row['overdue'] > 0)
                                                    <span class="text-danger fw-semibold">{{ $row['overdue'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-12 col-lg-7">
                <section class="admin-card" aria-labelledby="checkout-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="checkout-heading">Open checkouts</h2>
                            <p class="dashboard-section-subtitle">Tools currently in the field.</p>
                        </div>
                        <div class="dashboard-section-actions">
                            <a href="{{ route('admin.tools.index', ['status' => ToolStatus::CheckedOut->value]) }}" class="btn btn-sm btn-outline-secondary">View all</a>
                        </div>
                    </header>

                    @if($checkedOut->isEmpty())
                        <x-admin.empty-state icon="bi-box-arrow-up-right" title="No open checkouts">
                            Every tool is currently in storage.
                        </x-admin.empty-state>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Tool</th>
                                        <th>Holder</th>
                                        <th>Out</th>
                                        <th>Expected return</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($checkedOut as $co)
                                        @php
                                            $tool = $co->tool;
                                            $expected = $co->expected_return_at;
                                            $overdue = $expected !== null && $expected->isPast();
                                        @endphp
                                        <tr>
                                            <td>
                                                @if($tool)
                                                    <a href="{{ route('admin.tools.show', $tool) }}" class="text-decoration-none">{{ $tool->name }}</a>
                                                    <div class="text-muted small">{{ $tool->tool_code }}</div>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ $co->user?->name ?? '—' }}</td>
                                            <td class="text-nowrap text-muted small">{{ $co->checked_out_at?->format('Y-m-d') ?? '—' }}</td>
                                            <td class="text-nowrap {{ $overdue ? 'text-danger fw-semibold' : '' }}">
                                                {{ $expected?->format('Y-m-d') ?? '—' }}
                                                @if($overdue)
                                                    <i class="bi bi-exclamation-triangle-fill ms-1" aria-hidden="true"></i>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        </div>

        {{-- ============================================================
             ROW D: Maintenance Schedule + Inspection / Calibration
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-5">
                <section class="admin-card" aria-labelledby="maintenance-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="maintenance-heading">Maintenance schedule</h2>
                            <p class="dashboard-section-subtitle">Upcoming and overdue maintenance.</p>
                        </div>
                    </header>

                    @if($maintenanceOverdue->isEmpty() && $maintenanceDueThisWeek->isEmpty() && $maintenanceUpcoming->isEmpty())
                        <x-admin.empty-state icon="bi-wrench" title="No maintenance scheduled">
                            Add a maintenance record with a next due date to populate this list.
                        </x-admin.empty-state>
                    @else
                        @if($maintenanceOverdue->isNotEmpty())
                            <div class="maintenance-group maintenance-group--overdue">
                                <div class="maintenance-group__head">
                                    <span class="maintenance-group__title">
                                        <i class="bi bi-exclamation-triangle-fill text-danger" aria-hidden="true"></i> Overdue
                                    </span>
                                    <span class="maintenance-group__count">{{ $maintenanceOverdue->count() }}</span>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    @foreach($maintenanceOverdue as $rec)
                                        <li class="d-flex justify-content-between gap-2 small py-1">
                                            <span>
                                                <a href="{{ $rec->tool ? route('admin.tool-maintenance.index', $rec->tool) : '#' }}" class="text-decoration-none">
                                                    {{ $rec->tool?->name ?? 'Tool' }}
                                                </a>
                                                <span class="text-muted">· {{ $rec->type->label() }}</span>
                                            </span>
                                            <span class="text-danger fw-semibold text-nowrap">{{ $rec->next_due_at->format('Y-m-d') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($maintenanceDueThisWeek->isNotEmpty())
                            <div class="maintenance-group maintenance-group--due">
                                <div class="maintenance-group__head">
                                    <span class="maintenance-group__title">
                                        <i class="bi bi-clock-history text-warning" aria-hidden="true"></i> Due this week
                                    </span>
                                    <span class="maintenance-group__count">{{ $maintenanceDueThisWeek->count() }}</span>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    @foreach($maintenanceDueThisWeek as $rec)
                                        <li class="d-flex justify-content-between gap-2 small py-1">
                                            <span>
                                                <a href="{{ $rec->tool ? route('admin.tool-maintenance.index', $rec->tool) : '#' }}" class="text-decoration-none">
                                                    {{ $rec->tool?->name ?? 'Tool' }}
                                                </a>
                                                <span class="text-muted">· {{ $rec->type->label() }}</span>
                                            </span>
                                            <span class="text-warning fw-semibold text-nowrap">{{ $rec->next_due_at->format('Y-m-d') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if($maintenanceUpcoming->isNotEmpty())
                            <div class="maintenance-group maintenance-group--upcoming">
                                <div class="maintenance-group__head">
                                    <span class="maintenance-group__title">
                                        <i class="bi bi-calendar3 text-info" aria-hidden="true"></i> Upcoming
                                    </span>
                                    <span class="maintenance-group__count">{{ $maintenanceUpcoming->count() }}</span>
                                </div>
                                <ul class="list-unstyled mb-0">
                                    @foreach($maintenanceUpcoming as $rec)
                                        <li class="d-flex justify-content-between gap-2 small py-1">
                                            <span>
                                                <a href="{{ $rec->tool ? route('admin.tool-maintenance.index', $rec->tool) : '#' }}" class="text-decoration-none">
                                                    {{ $rec->tool?->name ?? 'Tool' }}
                                                </a>
                                                <span class="text-muted">· {{ $rec->type->label() }}</span>
                                            </span>
                                            <span class="text-muted text-nowrap">{{ $rec->next_due_at->format('Y-m-d') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @endif
                </section>
            </div>

            <div class="col-12 col-lg-7">
                <section class="admin-card" aria-labelledby="inspection-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="inspection-heading">Inspection &amp; calibration status</h2>
                            <p class="dashboard-section-subtitle">
                                Inspections act as the calibration signal for this fleet — there is no separate calibration entity.
                            </p>
                        </div>
                    </header>

                    <div class="inspection-buckets">
                        <div class="inspection-bucket inspection-bucket--danger">
                            <span class="inspection-bucket__label">Overdue</span>
                            <span class="inspection-bucket__value">{{ $inspectionBuckets['overdue']['count'] }}</span>
                        </div>
                        <div class="inspection-bucket inspection-bucket--warning">
                            <span class="inspection-bucket__label">Due within 14 days</span>
                            <span class="inspection-bucket__value">{{ $inspectionBuckets['due_soon']['count'] }}</span>
                        </div>
                        <div class="inspection-bucket inspection-bucket--info">
                            <span class="inspection-bucket__label">Upcoming</span>
                            <span class="inspection-bucket__value">{{ $inspectionBuckets['upcoming']['count'] }}</span>
                        </div>
                        <div class="inspection-bucket inspection-bucket--success">
                            <span class="inspection-bucket__label">Passed (last 30 days)</span>
                            <span class="inspection-bucket__value">{{ $inspectionBuckets['recently_passed']['count'] }}</span>
                        </div>
                    </div>

                    @if(($inspectionBuckets['overdue']['items'] ?? collect())->isNotEmpty())
                        <hr class="my-3">
                        <h3 class="h6 mb-2">Overdue inspections</h3>
                        <ul class="list-unstyled mb-0">
                            @foreach($inspectionBuckets['overdue']['items'] as $rec)
                                <li class="d-flex justify-content-between gap-2 small py-1">
                                    <span>
                                        <a href="{{ $rec->tool ? route('admin.tool-maintenance.index', $rec->tool) : '#' }}" class="text-decoration-none">
                                            {{ $rec->tool?->name ?? 'Tool' }}
                                        </a>
                                    </span>
                                    <span class="text-danger fw-semibold text-nowrap">{{ $rec->next_due_at->format('Y-m-d') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif(
                        $inspectionBuckets['overdue']['count'] === 0
                        && $inspectionBuckets['due_soon']['count'] === 0
                        && $inspectionBuckets['upcoming']['count'] === 0
                        && $inspectionBuckets['recently_passed']['count'] === 0
                    )
                        <hr class="my-3">
                        <x-admin.empty-state icon="bi-clipboard-check" title="No inspections recorded yet">
                            Record an inspection from any tool's maintenance tab to populate this panel.
                        </x-admin.empty-state>
                    @endif
                </section>
            </div>
        </div>

        {{-- ============================================================
             ROW E: Recent Activity + Recently Added + High-Value
             ============================================================ --}}
        <div class="dashboard-grid row g-3">
            <div class="col-12 col-lg-6">
                <section class="admin-card" aria-labelledby="activity-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="activity-heading">Recent tool activity</h2>
                            <p class="dashboard-section-subtitle">Newest tool-related events first.</p>
                        </div>
                        <div class="dashboard-section-actions">
                            <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-sm btn-outline-secondary">View history</a>
                        </div>
                    </header>

                    @if($recentActivity->isEmpty())
                        <x-admin.empty-state icon="bi-clock-history" title="No recent tool activity yet">
                            Tool events will appear here as they happen.
                        </x-admin.empty-state>
                    @else
                        <div class="activity-feed">
                            @foreach($recentActivity as $log)
                                @php
                                    $meta = $describeAction($log->action);
                                    $subjectInfo = $describeSubject($log);
                                @endphp
                                <x-admin.dashboard.activity-item
                                    :event="$meta['label']"
                                    :description="$log->action"
                                    :actor="$log->user?->name ?? 'System'"
                                    :subject="$subjectInfo['name']"
                                    :subjectHref="$subjectInfo['href']"
                                    :timestamp="$log->created_at"
                                    :icon="$meta['icon']"
                                    :variant="$meta['variant']" />
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-12 col-lg-3">
                <section class="admin-card" aria-labelledby="recent-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="recent-heading">Recently added</h2>
                            <p class="dashboard-section-subtitle">Newest tools.</p>
                        </div>
                    </header>

                    @if($recentTools->isEmpty())
                        <x-admin.empty-state icon="bi-tools" title="No tools yet">
                            @can('create', App\Models\Tool::class)
                                <a href="{{ route('admin.tools.create') }}">Register your first tool</a>.
                            @else
                                Tools registered in this workshop will appear here.
                            @endcan
                        </x-admin.empty-state>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach($recentTools as $tool)
                                <li class="py-2 border-bottom">
                                    <a href="{{ route('admin.tools.show', $tool) }}" class="d-block text-decoration-none">
                                        <span class="fw-semibold">{{ $tool->name }}</span>
                                        <span class="text-muted small d-block">{{ $tool->tool_code }}</span>
                                    </a>
                                    <div class="d-flex gap-2 align-items-center mt-1">
                                        <span class="text-muted small">{{ $tool->category?->name ?? 'Uncategorised' }}</span>
                                        @if($tool->status)
                                            <span class="admin-status-badge is-{{ $tool->status->color() }}">
                                                {{ $tool->status->label() }}
                                            </span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <div class="col-12 col-lg-3">
                <section class="admin-card" aria-labelledby="highvalue-heading">
                    <header class="dashboard-section-title">
                        <div>
                            <h2 id="highvalue-heading">High-value tools</h2>
                            <p class="dashboard-section-subtitle">By purchase price.</p>
                        </div>
                    </header>

                    @if($highValueTools->isEmpty())
                        <x-admin.empty-state icon="bi-cash-coin" title="No pricing recorded">
                            Tools with a recorded purchase price will appear here.
                        </x-admin.empty-state>
                    @else
                        <ul class="list-unstyled mb-0">
                            @foreach($highValueTools as $tool)
                                <li class="py-2 border-bottom">
                                    <a href="{{ route('admin.tools.show', $tool) }}" class="d-block text-decoration-none">
                                        <span class="fw-semibold">{{ $tool->name }}</span>
                                        <span class="text-muted small d-block">{{ $tool->category?->name ?? 'Uncategorised' }}</span>
                                    </a>
                                    <div class="d-flex gap-2 align-items-center justify-content-between mt-1">
                                        @if($tool->status)
                                            <span class="admin-status-badge is-{{ $tool->status->color() }}">
                                                {{ $tool->status->label() }}
                                            </span>
                                        @endif
                                        <span class="fw-semibold small">${{ number_format((float) $tool->purchase_price, 0) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>

    </div>
@endsection