{{--
    Row template for admin.departments.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (DepartmentController::search())
--}}

@forelse ($departments as $department)
    <tr>
        <td>
            <a href="{{ route('admin.departments.edit', $department) }}" class="text-decoration-none">
                {{ $department->name }}
            </a>
        </td>
        <td><code>{{ $department->code }}</code></td>
        <td>{{ $department->manager?->name ?? '—' }}</td>
        <td class="text-end">{{ number_format($department->equipment_count) }}</td>
        <td>
            <x-admin.status-badge :on="$department->is_active" />
        </td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.departments.edit', $department)" />
            <x-admin.actions.delete
                :action="route('admin.departments.destroy', $department)"
                confirm="Delete this department? Equipment in it must be moved first." />
        </td>
    </tr>
@empty
    <tr><td colspan="6">
        <x-admin.empty-state icon="bi-diagram-3" title="No departments yet">
            Create departments so equipment and inventory consumption can be attributed to an organizational unit.
        </x-admin.empty-state>
    </td></tr>
@endforelse
