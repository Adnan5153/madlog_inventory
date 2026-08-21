{{--
    Row template for admin.tool-maintenance.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (ToolMaintenanceController::search())
--}}

@forelse ($records as $record)
    <tr>
        <td>{{ $record->type->label() }}</td>
        <td class="text-nowrap">{{ $record->performed_at?->format('Y-m-d') ?? '—' }}</td>
        <td>{{ $record->performedBy?->name ?? '—' }}</td>
        <td>{{ $record->vendor ?? '—' }}</td>
        <td class="text-end">{{ $record->cost !== null ? '$'.number_format((float) $record->cost, 2) : '—' }}</td>
        <td>
            @if($record->next_due_at)
                <span class="{{ $record->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                    {{ $record->next_due_at->format('Y-m-d') }}
                    @if($record->isOverdue())<i class="bi bi-exclamation-triangle-fill ms-1"></i>@endif
                </span>
            @else
                —
            @endif
        </td>
        <td class="text-end text-nowrap">
            <x-admin.actions.view :href="route('admin.tool-maintenance.show', [$tool, $record])" />
            <x-admin.actions.edit :href="route('admin.tool-maintenance.edit', [$tool, $record])" />
            <x-admin.actions.delete
                :action="route('admin.tool-maintenance.destroy', [$tool, $record])"
                confirm="Delete this maintenance record?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7">
            <x-admin.empty-state icon="bi-wrench" title="No maintenance records yet">
                Record the first service to populate the history.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse
