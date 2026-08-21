{{--
    Shared row markup for the batteries list table. Used both:
      - server-side, by admin.batteries.index, to render the first page
      - client-side, by the live-search JSON endpoint (BatteryController::search)
--}}

@php
    use App\Enums\BatteryChemistry;
    use App\Enums\BatteryStatus;
    use App\Enums\StockStatus;
@endphp

@forelse ($batteries as $b)
    @php
        $chem = BatteryChemistry::tryFrom($b->battery_type);
        $stat = BatteryStatus::tryFrom($b->status);
        $stock = $b->stockStatus();
    @endphp
    <tr>
        <td>
            <a href="{{ route('admin.batteries.show', $b) }}" class="text-decoration-none">
                {{ $b->name }}
            </a>
            @if($b->brand)
                <div class="text-muted small">{{ $b->brand }}</div>
            @endif
        </td>
        <td class="text-nowrap">{{ $b->battery_code }}</td>
        <td>{{ $b->sku ?? '—' }}</td>
        <td>
            @if($chem)
                <span class="badge bg-{{ $chem->color() }}-subtle text-{{ $chem->color() }}-emphasis">{{ $chem->label() }}</span>
            @else
                {{ $b->battery_type }}
            @endif
        </td>
        <td>{{ $b->voltage !== null ? rtrim(rtrim(number_format((float) $b->voltage, 2), '0'), '.').' V' : '—' }}</td>
        <td>{{ $b->capacity_ah !== null ? number_format((float) $b->capacity_ah, 2) : '—' }}</td>
        <td>{{ $b->brand ?? '—' }}</td>
        <td>{{ $b->supplier?->name ?? '—' }}</td>
        <td>{{ $b->binLocation?->code ?? '—' }}</td>
        <td class="text-end">
            <span class="text-{{ $stock->color() }} fw-semibold">{{ number_format((float) $b->on_hand, 2) }}</span>
        </td>
        <td>
            @if($stat)
                <span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
            @else
                {{ $b->status }}
            @endif
            @if(! $b->is_active)
                <div class="text-muted small">Inactive</div>
            @endif
        </td>
        <td class="text-end text-nowrap">
            <x-admin.actions.view :href="route('admin.batteries.show', $b)" />
            <x-admin.actions.edit :href="route('admin.batteries.edit', $b)" />
            <x-admin.actions.delete
                :action="route('admin.batteries.destroy', $b)"
                confirm="Delete this battery?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="12">
            <x-admin.empty-state icon="bi-battery-charging" title="No batteries match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse
