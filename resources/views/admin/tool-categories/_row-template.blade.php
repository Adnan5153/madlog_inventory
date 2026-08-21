{{--
    Row template for admin.tool-categories.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (ToolCategoryController::search())
--}}

@forelse ($categories as $category)
    <tr>
        <td>{{ $category->name }}</td>
        <td class="text-muted">{{ $category->slug ?? '—' }}</td>
        <td class="text-end">{{ number_format($category->tools_count ?? 0) }}</td>
        <td><x-admin.status-badge :on="$category->is_active" /></td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.tool-categories.edit', $category)" />
            <x-admin.actions.delete
                :action="route('admin.tool-categories.destroy', $category)"
                confirm="Delete this category?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5">
            <x-admin.empty-state icon="bi-tags" title="No tool categories yet">
                Create one to organise the catalog.
            </x-admin.empty-state>
        </td>
    </tr>
@endforelse
