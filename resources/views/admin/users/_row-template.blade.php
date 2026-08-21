{{--
    Row template for admin.users.index. Used by both:
      - the server-side first-page render (via @include in index.blade.php)
      - the live-search JSON endpoint (UserController::search())
--}}

@forelse ($users as $user)
    <tr>
        <td>{{ $user->name }}</td>
        <td class="text-muted">{{ $user->email }}</td>
        <td>{{ $user->workshop?->name ?? '—' }}</td>
        <td>
            <x-admin.status-badge :on="$user->isAdmin()" :label="ucfirst($user->role ?? 'none')" />
        </td>
        <td>
            @forelse($user->rbacRoles as $r)
                <span class="badge text-bg-secondary me-1">{{ $r->name }}</span>
            @empty
                <span class="text-muted small">—</span>
            @endforelse
        </td>
        <td class="text-end">
            <x-admin.actions.edit :href="route('admin.users.edit', $user)" />
            <x-admin.actions.delete
                :action="route('admin.users.destroy', $user)"
                confirm="Delete this user?" />
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6">
            <x-admin.empty-state icon="bi-people" title="No users yet" />
        </td>
    </tr>
@endforelse
