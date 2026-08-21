@php
    use App\Enums\BatteryStockAdjustmentStatus;
@endphp

{{--
    Row template for admin.battery-stock-adjustments.index.
--}}

@forelse ($adjustments as $adjustment)
    @php
        $st = $adjustment->status instanceof BatteryStockAdjustmentStatus ? $adjustment->status : null;
    @endphp
    <tr>
        <td><a href="{{ route('admin.battery-stock-adjustments.show', $adjustment) }}" class="text-decoration-none">{{ $adjustment->reference }}</a></td>
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
            <x-admin.actions.view :href="route('admin.battery-stock-adjustments.show', $adjustment)" />
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-admin.empty-state icon="bi-sliders" title="No battery stock adjustments yet" /></td></tr>
@endforelse
