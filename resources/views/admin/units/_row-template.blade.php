{{--
    Row template for admin.units.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (UnitController::search())
--}}

@forelse ($units as $unit)
    <tr>
        <td>
            <a href="{{ route('admin.units.edit', $unit) }}" class="text-decoration-none">
                {{ $unit->name }}
            </a>
            @if($unit->description)
                <div class="text-muted small">{{ $unit->description }}</div>
            @endif
        </td>
        <td><code>{{ $unit->short_code }}</code></td>
        <td class="text-end">{{ $unit->decimal_precision }}</td>
        <td class="text-end">{{ number_format($unit->parts()->count()) }}</td>
        <td>
            <x-admin.status-badge :on="$unit->is_active" />
        </td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.units.edit', $unit)" />
            <x-admin.actions.delete
                :action="route('admin.units.destroy', $unit)"
                confirm="Delete this unit? Parts and bins using it must be reassigned first." />
        </td>
    </tr>
@empty
    <tr><td colspan="6">
        <x-admin.empty-state icon="bi-rulers" title="No units yet">
            Add units so parts can be measured in kg, L, pieces, etc.
        </x-admin.empty-state>
    </td></tr>
@endforelse
