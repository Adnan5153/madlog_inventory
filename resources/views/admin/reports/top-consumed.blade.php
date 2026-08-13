@extends('layouts.admin', ['title' => 'Top consumed'])

@section('content')
    <x-admin.breadcrumb :items="[['label' => 'Reports', 'url' => null], ['label' => 'Top consumed']]" />

    <x-admin.page-header title="Top consumed parts" subtitle="Outgoing quantities for the last {{ (int) $days }} days ({{ $from->format('Y-m-d') }} → {{ $to->format('Y-m-d') }}).">
        <x-slot:actions>
            <a href="{{ route('admin.reports.top-consumed.export', request()->only('days')) }}" class="btn btn-outline-secondary">
                <i class="bi bi-filetype-csv me-1"></i> Export CSV
            </a>
            <form method="GET" action="{{ route('admin.reports.top-consumed') }}" class="d-flex gap-2 align-items-center">
                <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="7"   @selected($days === 7)>Last 7 days</option>
                    <option value="30"  @selected($days === 30)>Last 30 days</option>
                    <option value="90"  @selected($days === 90)>Last 90 days</option>
                    <option value="365" @selected($days === 365)>Last 365 days</option>
                </select>
            </form>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-table">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Part</th>
                    <th>SKU</th>
                    <th class="text-end">Total consumed</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $i => $r)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $r->name }}</td>
                        <td class="text-muted">{{ $r->sku ?? '—' }}</td>
                        <td class="text-end fw-semibold">{{ number_format($r->total_out, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><x-admin.empty-state icon="bi-bar-chart" title="No outgoing movements" subtitle="Try a wider date range." /></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection