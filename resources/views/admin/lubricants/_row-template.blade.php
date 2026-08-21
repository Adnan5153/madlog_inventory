{{--
    Shared row markup for the lubricants list table. Used both:
      - server-side, by admin.lubricants.index, to render the first page
      - client-side, by the live-search JSON endpoint (LubricantController::search)
--}}

@php
    use App\Enums\LubricantApplication;
    use App\Enums\LubricantStatus;
    use App\Enums\LubricantType;
    use App\Enums\LubricantViscosity;
    use App\Enums\StockStatus;
@endphp

@forelse ($lubricants as $l)
    @php
        $type = LubricantType::tryFrom($l->lubricant_type);
        $viscosity = LubricantViscosity::tryFrom($l->viscosity_grade);
        $application = LubricantApplication::tryFrom($l->application_type);
        $stat = LubricantStatus::tryFrom($l->status);
        $stock = $l->stockStatus();
    @endphp
    <tr>
        <td>
            <a href="{{ route('admin.lubricants.show', $l) }}" class="text-decoration-none">
                {{ $l->name }}
            </a>
            @if($l->brand)
                <div class="text-muted small">{{ $l->brand }}</div>
            @endif
        </td>
        <td class="text-nowrap">{{ $l->lubricant_code }}</td>
        <td>{{ $l->sku ?? '—' }}</td>
        <td>
            @if($type)
                <span class="badge bg-{{ $type->color() }}-subtle text-{{ $type->color() }}-emphasis">{{ $type->label() }}</span>
            @else
                {{ $l->lubricant_type }}
            @endif
        </td>
        <td>
            @if($application)
                <span class="badge bg-{{ $application->color() }}-subtle text-{{ $application->color() }}-emphasis">{{ $application->label() }}</span>
            @else
                —
            @endif
        </td>
        <td class="text-nowrap">
            {{ number_format((float) $l->package_size, 2) }} {{ $l->package_unit }}
        </td>
        <td>{{ $l->binLocation?->code ?? '—' }}</td>
        <td class="text-end">
            <span class="text-{{ $stock->color() }} fw-semibold">{{ number_format((float) ($l->on_hand ?? 0), 2) }}</span>
        </td>
        <td>
            @if($stat)
                <span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
            @else
                {{ $l->status }}
            @endif
            @if(! $l->is_active)
                <div class="text-muted small">Inactive</div>
            @endif
        </td>
        <td class="text-end text-nowrap">
            <x-admin.actions.view :href="route('admin.lubricants.show', $l)" />
            <x-admin.actions.edit :href="route('admin.lubricants.edit', $l)" />
            <x-admin.actions.delete
                :action="route('admin.lubricants.destroy', $l)"
                confirm="Delete this lubricant?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="10">
            <x-admin.empty-state icon="bi-droplet" title="No lubricants match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse