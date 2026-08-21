{{--
    Row template for admin.purchase-orders.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (PurchaseOrderController::search())
--}}

@forelse ($orders as $order)
    <tr>
        <td><a href="{{ route('admin.purchase-orders.show', $order) }}" class="text-decoration-none">{{ $order->po_number }}</a></td>
        <td>{{ $order->supplier?->name ?? '—' }}</td>
        <td>{{ $order->creator?->name ?? '—' }}</td>
        <td><x-admin.status-badge :on="!in_array($order->status, ['cancelled','draft'])" :label="ucfirst(str_replace('_', ' ', $order->status))" /></td>
        <td>{{ $order->order_date?->format('Y-m-d') ?? '—' }}</td>
        <td class="text-end">{{ number_format($order->items_count) }}</td>
        <td class="text-end">${{ number_format($order->total, 2) }}</td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.purchase-orders.show', $order)" />
        </td>
    </tr>
@empty
    <tr><td colspan="8"><x-admin.empty-state icon="bi-bag" title="No purchase orders yet" /></td></tr>
@endforelse
