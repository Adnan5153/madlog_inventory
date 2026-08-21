@extends('layouts.admin', ['title' => 'Stock transfers'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Stock transfers']]" />

    <x-admin.page-header title="Stock transfers" subtitle="Inter-bin moves. Atomic decrement on the source, increment on the destination.">
        <x-slot:actions>
            <a href="{{ route('admin.stock-transfers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New transfer
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.filter-bar>
        <form method="GET" action="{{ route('admin.stock-transfers.index') }}"
              class="row g-2 flex-grow-1"
              data-live-search
              data-search-url="{{ route('admin.stock-transfers.search') }}">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label">Search</label>
                <input type="search" id="q" name="q" value="{{ $q }}" class="form-control" placeholder="Transfer number..."
                       autocomplete="off"
                       data-live-search-input>
            </div>
            <div class="col-6 col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select"
                        data-live-search-control>
                    <option value="">All</option>
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <x-admin.clear-filters :route="route('admin.stock-transfers.index')" />
        </form>
    </x-admin.filter-bar>

    <x-admin.live-search-status singular="transfer">
        {{ $transfers->total() }} {{ \Illuminate\Support\Str::plural('transfer', $transfers->total()) }}
    </x-admin.live-search-status>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Source</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Transferred by</th>
                    <th>Received by</th>
                    <th class="text-end">Lines</th>
                    <th class="text-end" style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody data-live-search-target>
                @include('admin.stock-transfers._row-template', ['transfers' => $transfers])
            </tbody>
        </table>
    </div>

    <div class="mt-3" data-live-search-pagination>
        {{ $transfers->links('vendor.pagination.bootstrap-5') }}
    </div>
@endsection