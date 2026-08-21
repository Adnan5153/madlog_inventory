{{--
    Row template for admin.stock-adjustments.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (StockAdjustmentController::search())
--}}

@forelse ($adjustments as $adjustment)
    <tr>
        <td><a href="{{ route('admin.stock-adjustments.show', $adjustment) }}" class="text-decoration-none">{{ $adjustment->adjustment_number }}</a></td>
        <td>{{ ucfirst(str_replace('_', ' ', $adjustment->reason)) }}</td>
        <td><x-admin.status-badge :on="$adjustment->status === 'applied'" :label="ucfirst($adjustment->status)" /></td>
        <td>{{ $adjustment->requester?->name ?? '—' }}</td>
        <td>{{ $adjustment->approver?->name ?? '—' }}</td>
        <td class="text-end">{{ number_format($adjustment->items_count) }}</td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.stock-adjustments.show', $adjustment)" />
        </td>
    </tr>
@empty
    <tr><td colspan="7"><x-admin.empty-state icon="bi-sliders" title="No stock adjustments yet" /></td></tr>
@endforelse
