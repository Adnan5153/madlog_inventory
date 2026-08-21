@extends('layouts.admin', ['title' => 'Tools'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Tools']]" />

    <x-admin.page-header title="Tools" subtitle="Workshop operational assets — wrenches, scanners, jacks, etc.">
        <x-slot:actions>
            <a href="{{ route('admin.tools.dashboard') }}" class="btn btn-outline-secondary">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
            <a href="{{ route('admin.tool-categories.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-tags me-1"></i> Categories
            </a>
            <a href="{{ route('admin.tools.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New tool
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.tools.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.tools.search') }}">
            <div class="col-12 col-md-4 col-lg-3">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}"
                       class="form-control"
                       placeholder="Name, code, serial, barcode, brand…"
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="category_id" class="form-label">Category</label>
                <select id="category_id" name="category_id" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected($categoryId == $cat->id)>{{ $cat->name }}</option>
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
                <label for="condition" class="form-label">Condition</label>
                <select id="condition" name="condition" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($conditions as $c)
                        <option value="{{ $c->value }}" @selected($condition === $c->value)>{{ $c->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="supplier_id" class="form-label">Supplier</label>
                <select id="supplier_id" name="supplier_id" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" @selected($supplierId == $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="requires_maintenance" class="form-label">Maintenance</label>
                <select id="requires_maintenance" name="requires_maintenance" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    <option value="yes" @selected($requiresMaintenance === 'yes')>Overdue only</option>
                    <option value="no" @selected($requiresMaintenance === 'no')>Not overdue</option>
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.tools.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status>
        <span data-live-search-count>{{ $tools->total() }} {{ \Illuminate\Support\Str::plural('tool', $tools->total()) }}</span>
    </x-admin.live-search-status>

    <div class="admin-table table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Condition</th>
                    <th>Bin</th>
                    <th>Holder</th>
                    <th>Supplier</th>
                    <th>Last maintenance</th>
                    <th>Next due</th>
                    <th class="text-end" style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.tools._row-template', ['tools' => $tools])
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        <div data-live-search-pagination>
            {{ $tools->links('vendor.pagination.bootstrap-5') }}
        </div>
    </div>
@endsection
