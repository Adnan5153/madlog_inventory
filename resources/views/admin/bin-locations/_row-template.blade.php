{{--
    Row template for admin.bin-locations.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (BinLocationController::search())
--}}

@forelse ($binLocations as $binLocation)
    <tr>
        <td><strong>{{ $binLocation->code }}</strong></td>
        <td>{{ $binLocation->zone ?? '—' }}</td>
        <td>{{ $binLocation->aisle ?? '—' }}</td>
        <td>{{ $binLocation->shelf ?? '—' }}</td>
        <td class="text-end">{{ number_format((float) ($binLocation->on_hand ?? 0), 2) }}</td>
        <td><x-admin.status-badge :on="$binLocation->is_active" /></td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.bin-locations.edit', $binLocation)" />
            <x-admin.actions.delete
                :action="route('admin.bin-locations.destroy', $binLocation)"
                confirm="Delete this bin location?" />
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-admin.empty-state icon="bi-geo-alt" title="No bin locations yet" /></td></tr>
@endforelse
