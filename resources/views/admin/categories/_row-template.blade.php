{{--
    Row template for admin.categories.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (CategoryController::search())
--}}

@forelse ($categories as $category)
    <tr>
        <td>
            <a href="{{ route('admin.categories.edit', $category) }}" class="text-decoration-none">
                {{ $category->name }}
            </a>
        </td>
        <td class="text-muted">{{ $category->slug }}</td>
        <td class="text-end">{{ number_format($category->parts_count) }}</td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.categories.edit', $category)" />
            <x-admin.actions.delete
                :action="route('admin.categories.destroy', $category)"
                confirm="Delete this category? Parts in it must be moved first." />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="4">
            <x-admin.empty-state icon="bi-tags" title="No categories match">
                Try a different search or clear the filters.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse