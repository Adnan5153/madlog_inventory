@extends('layouts.admin', ['title' => 'Equipment consumables'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Equipment consumables', 'url' => route('admin.equipment-consumables.index')],
        ['label' => 'List'],
    ]" />

    <x-admin.page-header title="Equipment consumables"
        subtitle="Track parts, batteries and lubricants assigned to each piece of equipment.">
        <x-slot:actions>
            <a href="{{ route('admin.equipment-consumables.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
            <a href="{{ route('admin.equipment-consumables.report.consumption') }}" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart me-1"></i> Consumption report
            </a>
            <a href="{{ route('admin.equipment-consumables.export') }}" class="btn btn-outline-secondary">
                <i class="bi bi-download me-1"></i> Export CSV
            </a>
            @can('create', App\Models\EquipmentConsumable::class)
                <a href="{{ route('admin.equipment-consumables.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Assign consumable
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.equipment-consumables.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.equipment-consumables.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control"
                       placeholder="Equipment name, asset #, notes…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="resource_type" class="form-label">Resource type</label>
                <select id="resource_type" name="resource_type" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($resourceTypes as $class => $label)
                        <option value="{{ $class }}" @selected($resourceType === $class)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($statuses as $st)
                        <option value="{{ $st->value }}" @selected($status === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="open" class="form-label">Open only</label>
                <select id="open" name="open" class="form-select" data-live-search-control>
                    <option value="">Any</option>
                    <option value="1" @selected($open)>Active</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="replacement" class="form-label">Replacement</label>
                <select id="replacement" name="replacement" class="form-select" data-live-search-control>
                    <option value="">Any</option>
                    <option value="due" @selected(request('replacement') === 'due')>Due within 30 days</option>
                    <option value="due_soon" @selected(request('replacement') === 'due_soon')>Due this week</option>
                    <option value="overdue" @selected(request('replacement') === 'overdue')>Overdue</option>
                </select>
                {{-- Hidden inputs so the existing controller booleans stay in sync. --}}
                <input type="hidden" name="due" value="{{ request('replacement') === 'due' ? '1' : '' }}">
                <input type="hidden" name="due_soon" value="{{ request('replacement') === 'due_soon' ? '1' : '' }}">
                <input type="hidden" name="overdue" value="{{ request('replacement') === 'overdue' ? '1' : '' }}">
            </div>
            <x-admin.clear-filters :route="route('admin.equipment-consumables.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status>
        <span data-live-search-count>{{ $consumables->total() }} {{ \Illuminate\Support\Str::plural('consumable', $consumables->total()) }}</span>
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Equipment</th>
                    <th>Resource</th>
                    <th>Status</th>
                    <th class="num">Quantity</th>
                    <th>Expected replacement</th>
                    <th>Assigned</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.equipment-consumables._row-template', ['consumables' => $consumables])
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <div data-live-search-pagination>
            {{ $consumables->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>
@endsection