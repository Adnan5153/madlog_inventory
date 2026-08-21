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
        <form method="GET" action="{{ route('admin.audit-logs.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.audit-logs.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Action contains</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="e.g. created, updated..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="user_id" class="form-label">User</label>
                <select id="user_id" name="user_id" class="form-select"
                        data-live-search-control>
                    <option value="">All</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" @selected((string)($filters['userId'] ?? '') === (string)$u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="action" class="form-label">Action prefix</label>
                <select id="action" name="action" class="form-select"
                        data-live-search-control>
                    <option value="">All</option>
                    @foreach($actions as $a)
                        <option value="{{ $a }}" @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="subject_type" class="form-label">Model</label>
                <select id="subject_type" name="subject_type" class="form-select"
                        data-live-search-control>
                    <option value="">All</option>
                    @foreach($subjectTypes as $t)
                        <option value="{{ $t }}" @selected(($filters['subjectType'] ?? '') === $t)>{{ class_basename($t) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="from" class="form-label">From</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control"
                       data-live-search-control>
            </div>
            <div class="col-6 col-md-2">
                <label for="to" class="form-label">To</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control"
                       data-live-search-control>
            </div>
            <x-admin.clear-filters :route="route('admin.audit-logs.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="auditLog">
        {{ $logs->total() }} {{ \Illuminate\Support\Str::plural('auditLog', $logs->total()) }}
    </x-admin.live-search-status>

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
            <tbody data-live-search-target>
                @include('admin.audit-logs._row-template', ['logs' => $logs])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $logs->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection