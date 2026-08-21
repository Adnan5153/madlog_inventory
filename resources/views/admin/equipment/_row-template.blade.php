{{--
    Row template for admin.equipment.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (EquipmentController::search())
--}}

@forelse ($equipment as $equipment)
    <tr>
        <td>
            <a href="{{ route('admin.equipment.show', $equipment) }}" class="text-decoration-none">
                {{ $equipment->name }}
            </a>
            <div class="text-muted small">{{ $equipment->model }}</div>
        </td>
        <td><code>{{ $equipment->asset_number ?? '—' }}</code></td>
        <td>{{ $equipment->department?->name ?? '—' }}</td>
        <td class="text-muted">{{ $equipment->manufacturer ?? '—' }}</td>
        <td>
            <x-admin.status-badge :variant="match($equipment->status) {
                'active' => 'success',
                'maintenance' => 'warning',
                'retired', 'disposed' => 'danger',
                default => 'default',
            }">
                {{ ucfirst($equipment->status) }}
            </x-admin.status-badge>
        </td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.equipment.edit', $equipment)" />
            <x-admin.actions.delete
                :action="route('admin.equipment.destroy', $equipment)"
                confirm="Delete this equipment? This action cannot be undone." />
        </td>
    </tr>
@empty
    <tr><td colspan="6">
        <x-admin.empty-state icon="bi-tools" title="No equipment yet">
            Add the asset register so equipment can be linked to inventory consumption.
        </x-admin.empty-state>
    </td></tr>
@endforelse
