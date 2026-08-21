{{--
    Shared row markup for the tools list table. Used both:
      - server-side, by admin.tools.index, to render the first page
      - client-side, by the live-search JSON endpoint (ToolController::search)
--}}

@php
    use App\Enums\ToolCondition;
    use App\Enums\ToolStatus;
@endphp

@forelse ($tools as $tool)
    @php
        $stat = $tool->status instanceof ToolStatus ? $tool->status : ToolStatus::tryFrom($tool->status);
        $cond = $tool->condition instanceof ToolCondition ? $tool->condition : ToolCondition::tryFrom($tool->condition);
        $lastMaint = $tool->lastMaintenanceAt();
        $nextDue = $tool->nextMaintenanceDueAt();
        $nextDueOverdue = $nextDue === null
            ? false
            : (bool) $tool->maintenanceRecords()
                ->whereNotNull('next_due_at')
                ->where('next_due_at', '<', now())
                ->exists();
    @endphp
    <tr>
        <td>
            <a href="{{ route('admin.tools.show', $tool) }}" class="text-decoration-none">
                {{ $tool->name }}
            </a>
            <div class="text-muted small">
                {{ $tool->tool_code }}
                @if($tool->brand) · {{ $tool->brand }} @endif
                @if($tool->model) · {{ $tool->model }} @endif
            </div>
        </td>
        <td>{{ $tool->category?->name ?? '—' }}</td>
        <td>
            @if($stat)
                <span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
            @else
                {{ $tool->status }}
            @endif
            @if(! $tool->is_active)
                <div class="text-muted small">Inactive</div>
            @endif
        </td>
        <td>
            @if($cond)
                <span class="badge bg-{{ $cond->color() }}-subtle text-{{ $cond->color() }}-emphasis">{{ $cond->label() }}</span>
            @else
                {{ $tool->condition }}
            @endif
        </td>
        <td>{{ $tool->binLocation?->code ?? '—' }}</td>
        <td>{{ $tool->currentHolder?->name ?? '—' }}</td>
        <td>{{ $tool->supplier?->name ?? '—' }}</td>
        <td>{{ $lastMaint?->format('Y-m-d') ?? '—' }}</td>
        <td>
            @if($nextDue)
                <span class="{{ $nextDueOverdue ? 'text-danger fw-semibold' : '' }}">
                    {{ $nextDue->format('Y-m-d') }}
                    @if($nextDueOverdue)
                        <i class="bi bi-exclamation-triangle-fill ms-1"></i>
                    @endif
                </span>
            @else
                —
            @endif
        </td>
        <td class="text-end text-nowrap">
            <x-admin.actions.view :href="route('admin.tools.show', $tool)" />
            <x-admin.actions.edit :href="route('admin.tools.edit', $tool)" />
            <x-admin.actions.delete
                :action="route('admin.tools.destroy', $tool)"
                confirm="Delete this tool?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="10">
            <x-admin.empty-state icon="bi-tools" title="No tools match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse
