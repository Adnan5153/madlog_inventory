{{--
    Row template for admin.audit-logs.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (AuditLogController::search())
--}}

@forelse ($logs as $log)
    <tr>
        <td class="small text-muted">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
        <td>{{ $log->user?->name ?? '—' }}</td>
        <td><x-admin.status-badge variant="info">{{ $log->action }}</x-admin.status-badge></td>
        <td class="text-muted">{{ $log->subject_type ? class_basename($log->subject_type) . ($log->subject_id ? " #{$log->subject_id}" : '') : '—' }}</td>
        <td class="text-muted">{{ $log->workshop_id ?? '—' }}</td>
        <td class="text-end">
            <button type="button"
                    class="btn-inspect"
                    data-drawer-open
                    data-drawer-url="{{ route('admin.audit-logs.show', $log) }}"
                    data-drawer-title="Audit log #{{ $log->id }}"
                    title="Inspect">
                <i class="bi bi-eye" aria-hidden="true"></i>
            </button>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <x-admin.empty-state icon="bi-clock-history" title="No matching log entries" />
        </td>
    </tr>
@endforelse
