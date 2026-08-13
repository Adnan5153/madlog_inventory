@csrf

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name</label>
            <input type="text" id="name" name="name" value="{{ old('name', $role->name ?? '') }}" class="form-control" required>
            @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="slug" class="form-label">Slug</label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $role->slug ?? '') }}" class="form-control" required pattern="[a-z0-9\-]+">
            <div class="form-text">Lowercase letters, numbers, dashes.</div>
            @error('slug')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="description" class="form-label">Description</label>
            <textarea id="description" name="description" rows="2" class="form-control">{{ old('description', $role->description ?? '') }}</textarea>
            @error('description')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="admin-card mt-3">
    <h2 class="h6 mb-3">Permissions</h2>
    <p class="text-muted small mb-3">Tick the abilities this role grants. Permissions are grouped by domain; expand a group to see all members.</p>

    @php
        $checked = old('permissions', $rolePermIds ?? []);
    @endphp

    <div class="accordion" id="permissions-accordion">
        @foreach($grouped as $group => $perms)
            @php
                $groupIds = $perms->pluck('id')->all();
                $groupChecked = count(array_intersect($groupIds, $checked)) === count($groupIds);
            @endphp
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-{{ $group }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $group }}" aria-expanded="false" aria-controls="collapse-{{ $group }}">
                        <span class="me-2"><strong>{{ ucfirst($group) }}</strong></span>
                        <span class="text-muted small">{{ count($perms) }} permission(s)</span>
                    </button>
                </h2>
                <div id="collapse-{{ $group }}" class="accordion-collapse collapse" data-bs-parent="#permissions-accordion">
                    <div class="accordion-body">
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input group-all" data-group="{{ $group }}" id="group-all-{{ $group }}" @checked($groupChecked)>
                            <label class="form-check-label" for="group-all-{{ $group }}">Grant all in this group</label>
                        </div>
                        <div class="row g-2">
                            @foreach($perms as $perm)
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $perm->id }}"
                                               class="form-check-input perm-{{ $group }}"
                                               id="perm-{{ $perm->id }}"
                                               @checked(in_array($perm->id, $checked, true))>
                                        <label class="form-check-label" for="perm-{{ $perm->id }}">
                                            <code>{{ $perm->name }}</code>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        (function () {
            document.querySelectorAll('.group-all').forEach(function (box) {
                box.addEventListener('change', function () {
                    var group = this.getAttribute('data-group');
                    document.querySelectorAll('.perm-' + group).forEach(function (cb) {
                        cb.checked = box.checked;
                    });
                });
            });
        })();
    </script>
</div>
