{{--
    Row template for admin.supplier-categories.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (SupplierCategoryController::search())
--}}

@forelse ($supplierCategories as $supplierCategory)
    <tr>
        <td>{{ $supplierCategory->name }}</td>
        <td class="text-muted">{{ $supplierCategory->code }}</td>
        <td class="text-end">{{ number_format($supplierCategory->suppliers_count) }}</td>
        <td><x-admin.status-badge :on="$supplierCategory->is_active" /></td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.supplier-categories.edit', $supplierCategory)" />
            <x-admin.actions.delete
                :action="route('admin.supplier-categories.destroy', $supplierCategory)"
                confirm="Delete this category?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5"><x-admin.empty-state icon="bi-tags" title="No supplier categories yet" /></td>
    </tr>
@endforelse
