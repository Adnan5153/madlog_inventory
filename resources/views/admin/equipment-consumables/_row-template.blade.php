{{--
    Shared row markup for the equipment consumables list. Used both:
      - server-side, by admin.equipment-consumables.index
      - client-side, by the live-search JSON endpoint (EquipmentConsumableController::search)
--}}

@forelse ($consumables as $consumable)
    @php
        $resource = $consumable->resource;
        $resourceType = $consumable->resource_type;
        $typeLabel = \App\Models\EquipmentConsumable::resourceLabel($resourceType);
        $typeIcon = \App\Models\EquipmentConsumable::resourceIcon($resourceType);
        $current = $consumable->currentAssignment;
        $status = $current?->status;
        $statusColor = $status instanceof \App\Enums\EquipmentConsumableStatus ? $status->color() : 'secondary';
        $statusLabel = $status instanceof \App\Enums\EquipmentConsumableStatus ? $status->label() : 'Closed';
        $expected = $consumable->expected_replacement_at;
        $overdue = $expected && $expected->startOfDay()->lt(now()->startOfDay());
        $dueSoon = $expected && ! $overdue && $expected->startOfDay()->lte(now()->addDays(7)->startOfDay());
        $resourceName = $resource
            ? ($resource->name ?? $resource->battery_code ?? $resource->lubricant_code ?? 'Resource')
            : 'Resource #'.$consumable->resource_id;
        $resourceCode = $resource
            ? ($resource->sku ?? $resource->battery_code ?? $resource->lubricant_code ?? null)
            : null;
    @endphp
    <tr>
        <td>
            <a href="{{ route('admin.equipment-consumables.show', $consumable) }}" class="text-decoration-none">
                {{ $consumable->equipment?->name ?? 'Equipment #'.$consumable->equipment_id }}
            </a>
            <div class="text-muted small">
                {{ $consumable->equipment?->asset_number ?? '—' }}
            </div>
        </td>
        <td>
            <span class="eq-resource-pill eq-resource-pill--{{ \Illuminate\Support\Str::afterLast($resourceType, '\\') }}">
                <i class="bi {{ $typeIcon }}" aria-hidden="true"></i>{{ $typeLabel }}
            </span>
            <div class="text-muted small">{{ $resourceName }}@if($resourceCode) · {{ $resourceCode }}@endif</div>
        </td>
        <td>
            <span class="admin-status-badge is-{{ $statusColor }}">{{ $statusLabel }}</span>
        </td>
        <td class="num">
            {{ $current ? number_format((float) $current->quantity, 3) : '—' }}
        </td>
        <td>
            @if($expected)
                <span class="{{ $overdue ? 'text-danger fw-semibold' : ($dueSoon ? 'text-warning fw-semibold' : '') }}">
                    {{ $expected->format('Y-m-d') }}
                    @if($overdue)
                        <i class="bi bi-exclamation-triangle-fill ms-1" aria-hidden="true"></i>
                    @endif
                </span>
            @else
                —
            @endif
        </td>
        <td>{{ $consumable->assigned_at?->format('Y-m-d') ?? '—' }}</td>
        <td class="text-end text-nowrap">
            <x-admin.actions.view :href="route('admin.equipment-consumables.show', $consumable)" />
            <x-admin.actions.edit :href="route('admin.equipment-consumables.edit', $consumable)" />
            <x-admin.actions.delete
                :action="route('admin.equipment-consumables.destroy', $consumable)"
                confirm="Delete this consumable record?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="7">
            <x-admin.empty-state icon="bi-link-45deg" title="No consumables match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse