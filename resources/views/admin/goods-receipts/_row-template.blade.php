{{--
    Row template for admin.goods-receipts.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (GoodsReceiptController::search())
--}}

@forelse ($receipts as $receipt)
    <tr>
        <td><a href="{{ route('admin.goods-receipts.show', $receipt) }}" class="text-decoration-none">{{ $receipt->grn_number }}</a></td>
        <td>{{ $receipt->purchaseOrder?->po_number ?? '—' }}</td>
        <td>{{ $receipt->purchaseOrder?->supplier?->name ?? '—' }}</td>
        <td>{{ $receipt->receiver?->name ?? '—' }}</td>
        <td><x-admin.status-badge :on="$receipt->status !== 'disputed'" :label="ucfirst($receipt->status)" /></td>
        <td>{{ $receipt->received_at?->format('Y-m-d H:i') ?? '—' }}</td>
        <td class="text-end">{{ number_format($receipt->items_count) }}</td>
    </tr>
@empty
    <tr><td colspan="7"><x-admin.empty-state icon="bi-box-seam" title="No goods receipts yet" /></td></tr>
@endforelse
