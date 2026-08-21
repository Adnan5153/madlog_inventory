{{--
    Shared row markup for the products list table.

    Used both:
      - server-side, by admin.products.index, to render the first page
        of results (via @include with $parts as the paginator), and
      - client-side, by the live-search JSON endpoint
        (ProductController::search), which renders this template
        once per row with $parts set to a Collection of matching parts.

    When you change the columns shown here, mirror the change in
    admin.products.index.
--}}

@forelse ($parts as $p)
    <tr>
        <td>
            <a href="{{ route('admin.products.show', $p) }}" class="text-decoration-none">
                {{ $p->name }}
            </a>
        </td>
        <td>{{ $p->sku ?? '—' }}</td>
        <td>{{ $p->oem_part_number ?? '—' }}</td>
        <td>{{ $p->barcode ?? '—' }}</td>
        <td>{{ $p->category?->name ?? '—' }}</td>
        <td>{{ $p->unit?->short_code ?? '—' }}</td>
        <td>{{ $p->brand ?? '—' }}</td>
        <td>@include('admin.products._storage-cell', ['p' => $p, 'compact' => true])</td>
        <td class="text-end">{{ number_format((float) $p->cost_price, 2) }}</td>
        <td class="text-end">
            @php $oh = (float) ($p->on_hand ?? 0); @endphp
            <span class="{{ $oh <= (float) $p->reorder_threshold ? 'text-danger fw-semibold' : '' }}">
                {{ number_format($oh, 2) }}
            </span>
        </td>
        <td class="text-end">{{ number_format($p->reorder_threshold) }}</td>
        <td class="text-end">{{ number_format($p->reorder_quantity) }}</td>
        <td>
            <x-admin.status-badge :on="$p->is_active" />
        </td>
        <td class="text-nowrap">{{ $p->created_at?->format('Y-m-d') ?? '—' }}</td>
        <td class="text-end">
            <x-admin.actions.view :href="route('admin.products.show', $p)" />
            <x-admin.actions.edit :href="route('admin.products.edit', $p)" />
            <x-admin.actions.delete
                :action="route('admin.products.destroy', $p)"
                confirm="Delete this product?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="15">
            <x-admin.empty-state icon="bi-box-seam" title="No products match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse