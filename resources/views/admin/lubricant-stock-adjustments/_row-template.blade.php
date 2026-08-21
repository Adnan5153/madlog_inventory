@php
    use App\Enums\LubricantStockAdjustmentStatus;
@endphp

{{--
    Row template for admin.lubricant-stock-adjustments.index.
--}}

@forelse ($adjustments as $adjustment)
    @php
        $st = $adjustment->status instanceof LubricantStockAdjustmentStatus ? $adjustment->status : null;
    @endphp
    <tr>
        <td><a href="{{ route('admin.lubricant-stock-adjustments.show', $adjustment) }}" class="text-decoration-none">{{ $adjustment->reference }}</a></td>
        <td>{{ ucfirst(str_replace('_', ' ', $adjustment->reason)) }}</td>
        <td>
            @if($st)
                <span class="badge bg-{{ $st->color() }}-subtle text-{{ $st->color() }}-emphasis">{{ $st->label() }}</span>
            @else
                {{ ucfirst($adjustment->status) }}
            @endif
        </td>
        <td>{{ $adjustment->requester?->name ?? '—' }}</td>
        <td>{{ $adjustment->approver?->name ?? '—' }}</td>
        <td class="text-end">{{ number_format($adjustment->items_count) }}</td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.lubricant-stock-adjustments.show', $adjustment)" />
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-admin.empty-state icon="bi-sliders" title="No lubricant stock adjustments yet" /></td></tr>
@endforelse