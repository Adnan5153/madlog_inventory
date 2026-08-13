@extends('layouts.admin', ['title' => 'Audit log detail'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Audit logs', 'url' => route('admin.audit-logs.index')],
        ['label' => '#' . $log->id],
    ]" />

    <x-admin.page-header title="Audit log #{{ $log->id }}" :subtitle="$log->action" />

    <div class="admin-card">
        <dl class="row mb-0">
            <dt class="col-3 text-muted">When</dt><dd class="col-9">{{ $log->created_at->format('Y-m-d H:i:s') }}</dd>
            <dt class="col-3 text-muted">User</dt><dd class="col-9">{{ $log->user?->name ?? '—' }} ({{ $log->user?->email ?? '—' }})</dd>
            <dt class="col-3 text-muted">Action</dt><dd class="col-9"><code>{{ $log->action }}</code></dd>
            <dt class="col-3 text-muted">Subject</dt><dd class="col-9">{{ $log->subject_type ? class_basename($log->subject_type) . ' #' . $log->subject_id : '—' }}</dd>
            <dt class="col-3 text-muted">Workshop</dt><dd class="col-9">{{ $log->workshop?->name ?? ($log->workshop_id ?? '—') }}</dd>
            <dt class="col-3 text-muted">IP</dt><dd class="col-9">{{ $log->ip_address ?? '—' }}</dd>
            <dt class="col-3 text-muted">User agent</dt><dd class="col-9"><small>{{ $log->user_agent ?? '—' }}</small></dd>
        </dl>
    </div>

    <div class="admin-card mt-3">
        <h2 class="h6 mb-3">Changes</h2>
        <pre class="bg-light p-3 rounded small mb-0" style="max-height: 600px; overflow:auto;">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
@endsection