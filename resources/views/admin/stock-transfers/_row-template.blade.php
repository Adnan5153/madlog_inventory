{{--
    Row template for admin.stock-transfers.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (StockTransferController::search())
--}}

@forelse ($transfers as $transfer)
    <tr>
        <td><a href="{{ route('admin.stock-transfers.show', $transfer) }}" class="text-decoration-none">{{ $transfer->transfer_number }}</a></td>
        <td>{{ $transfer->sourceBin?->code ?? '—' }}</td>
        <td>{{ $transfer->destinationBin?->code ?? '—' }}</td>
        <td><x-admin.status-badge :on="$transfer->status === 'received'" :label="ucfirst(str_replace('_', ' ', $transfer->status))" /></td>
        <td>{{ $transfer->transferer?->name ?? '—' }}</td>
        <td>{{ $transfer->receiver?->name ?? '—' }}</td>
        <td class="text-end">{{ number_format($transfer->items_count) }}</td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.stock-transfers.show', $transfer)" />
        </td>
    </tr>
@empty
    <tr><td colspan="8"><x-admin.empty-state icon="bi-arrow-left-right" title="No stock transfers yet" /></td></tr>
@endforelse
