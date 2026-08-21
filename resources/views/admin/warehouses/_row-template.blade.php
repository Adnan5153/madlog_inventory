{{--
    Row template for admin.warehouses.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (WarehouseController::search())
--}}

@forelse ($warehouses as $warehouse)
    <tr>
        <td><a href="{{ route('admin.warehouses.show', $warehouse) }}" class="text-decoration-none">{{ $warehouse->name }}</a></td>
        <td class="text-muted">{{ $warehouse->slug ?? '—' }}</td>
        <td class="text-end">{{ number_format($warehouse->bin_locations_count) }}</td>
        <td class="text-end">{{ number_format($warehouse->parts_count) }}</td>
        <td class="text-end">{{ number_format($warehouse->suppliers_count) }}</td>
        <td class="text-end">{{ number_format($warehouse->users_count) }}</td>
        <td><x-admin.status-badge :on="$warehouse->is_active" /></td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.warehouses.show', $warehouse)" />
            @if($user?->isAdmin() && $user?->workshop_id === null)
                <x-admin.actions.edit :href="route('admin.warehouses.edit', $warehouse)" />
                <x-admin.actions.delete
                    :action="route('admin.warehouses.destroy', $warehouse)"
                    icon="bi-archive"
                    confirm="Archive this warehouse? Records stay in the database." />
            @endif
        </td>
    </tr>
@empty
    <tr><td colspan="8"><x-admin.empty-state icon="bi-building" title="No warehouses yet" /></td></tr>
@endforelse
