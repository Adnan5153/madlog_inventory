@extends('layouts.admin', ['title' => 'Lubricant stock adjustments'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Lubricant stock adjustments']]" />

    <x-admin.page-header title="Lubricant stock adjustments" subtitle="Cycle counts, shrinkage, damage, spillage — anything that moves on-hand without a PO or transfer.">
        <x-slot:actions>
            <a href="{{ route('admin.lubricant-stock-adjustments.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New adjustment
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.lubricant-stock-adjustments.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.lubricant-stock-adjustments.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control"
                       placeholder="Reference, reason..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select" data-live-search-control>
                    <option value="">All</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.lubricant-stock-adjustments.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="lubricant adjustment">
        {{ $adjustments->total() }} {{ \Illuminate\Support\Str::plural('adjustment', $adjustments->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Requested by</th>
                    <th>Approved by</th>
                    <th class="text-end">Lines</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.lubricant-stock-adjustments._row-template', ['adjustments' => $adjustments])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $adjustments->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection