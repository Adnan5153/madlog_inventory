@csrf

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" class="form-control" required>
            @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" class="form-control" required>
            @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="password" class="form-label">Password @if(!empty($user->exists))<span class="text-muted small">(leave blank to keep)</span>@endif</label>
            <input type="password" id="password" name="password" class="form-control" @if(empty($user->exists)) required @endif autocomplete="new-password">
            @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">Confirm password</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" @if(empty($user->exists)) required @endif autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label for="role" class="form-label">Role</label>
            <select id="role" name="role" class="form-select" required>
                <option value="admin" @selected(old('role', $user->role ?? '') === 'admin')>Admin</option>
                <option value="staff" @selected(old('role', $user->role ?? '') === 'staff')>Staff</option>
            </select>
            <div class="form-text">Admins implicitly satisfy every permission; staff get only RBAC-granted abilities.</div>
            @error('role')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="workshop_id" class="form-label">Workshop (optional)</label>
            <select id="workshop_id" name="workshop_id" class="form-select">
                <option value="">— Global admin —</option>
                @foreach($workshops as $w)
                    <option value="{{ $w->id }}" @selected((string) old('workshop_id', $user->workshop_id ?? '') === (string) $w->id)>{{ $w->name }}</option>
                @endforeach
            </select>
            @error('workshop_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label">RBAC roles</label>
            <div class="row g-2">
                @foreach($roles as $r)
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="checkbox" id="rbac-{{ $r->id }}" name="rbac_roles[]" value="{{ $r->id }}" class="form-check-input"
                                @checked(in_array($r->id, old('rbac_roles', isset($user) ? $user->rbacRoles->pluck('id')->all() : []), true))>
                            <label class="form-check-label" for="rbac-{{ $r->id }}">{{ $r->name }}</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="form-text">Effective permissions = role union + direct grants. Admins get everything regardless.</div>
            @error('rbac_roles')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
