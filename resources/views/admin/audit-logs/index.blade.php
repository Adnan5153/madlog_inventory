@extends('layouts.admin', ['title' => 'Audit logs'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Audit logs']]" />

    <x-admin.page-header title="Audit logs" subtitle="Append-only history of every model mutation. Rows cannot be edited or deleted.">
        <x-slot:actions>
            <a href="{{ route('admin.audit-logs.export', request()->query()) }}" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-2 flex-grow-1">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Action contains</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="e.g. created, updated...">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="user_id" class="form-label">User</label>
                <select id="user_id" name="user_id" class="form-select">
                    <option value="">All</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string)($filters['userId'] ?? '') === (string)$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="action" class="form-label">Action prefix</label>
                <select id="action" name="action" class="form-select">
                    <option value="">All</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="subject_type" class="form-label">Model</label>
                <select id="subject_type" name="subject_type" class="form-select">
                    <option value="">All</option>
                    @foreach($subjectTypes as $t)
                        <option value="{{ $t }}" @selected(($filters['subjectType'] ?? '') === $t)>{{ class_basename($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-md-auto align-self-end">
                <button class="btn btn-outline-secondary">
                    <i class="bi bi-search"></i> Apply
                </button>
            </div>
        </form>
    </x-admin.filter-bar>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>When</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>Workshop</th>
                    <th class="text-end" style="width: 80px;">View</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>{{ $log->user?->name ?? '—' }}</td>
                        <td><x-admin.status-badge variant="info">{{ $log->action }}</x-admin.status-badge></td>
                        <td class="text-muted">{{ $log->subject_type ? class_basename($log->subject_type) . ($log->subject_id ? " #{$log->subject_id}" : '') : '—' }}</td>
                        <td class="text-muted">{{ $log->workshop_id ?? '—' }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.audit-logs.show', $log) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty-state icon="bi-clock-history" title="No matching log entries" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $logs->links('vendor.pagination.bootstrap-5') }}</div>
@endsection