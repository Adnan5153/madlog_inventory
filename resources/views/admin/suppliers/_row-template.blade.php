{{--
    Row template for admin.suppliers.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (SupplierController::search())
--}}

@forelse ($suppliers as $supplier)
    <tr>
        <td>
            <a href="{{ route('admin.suppliers.edit', $supplier) }}" class="text-decoration-none">{{ $supplier->name }}</a>
            @if($supplier->tax_id)
                <div class="small text-muted">Tax ID: {{ $supplier->tax_id }}</div>
            @endif
        </td>
        <td>{{ $supplier->contact_name ?? '—' }}</td>
        <td>
            <div>{{ $supplier->email ?? '—' }}</div>
            <div class="small text-muted">{{ $supplier->phone ?? '—' }}</div>
        </td>
        <td>{{ $supplier->category?->name ?? '—' }}</td>
        <td><x-admin.status-badge :on="$supplier->is_active" /></td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.suppliers.edit', $supplier)" />
            <x-admin.actions.delete
                :action="route('admin.suppliers.destroy', $supplier)"
                confirm="Delete this supplier?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <x-admin.empty-state icon="bi-truck" title="No suppliers yet">
                Add a vendor to start tracking purchase orders.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse
