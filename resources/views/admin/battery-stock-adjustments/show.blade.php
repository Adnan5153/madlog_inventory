@php
    use App\Enums\BatteryStockAdjustmentStatus;
@endphp

@extends('layouts.admin', ['title' => $adjustment->reference])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Battery stock adjustments', 'url' => route('admin.battery-stock-adjustments.index')],
        ['label' => $adjustment->reference],
    ]" />

    @if (session('status'))
        <x-admin.alert variant="success">{{ session('status') }}</x-admin.alert>
    @endif
    @error('adjustment')
        <x-admin.alert variant="danger">{{ $message }}</x-admin.alert>
    @enderror

    <x-admin.page-header :title="$adjustment->reference">
        <x-slot:actions>
            @if(($adjustment->status instanceof BatteryStockAdjustmentStatus) && $adjustment->status === BatteryStockAdjustmentStatus::Pending && auth()->user()?->isAdmin())
                <form method="POST" action="{{ route('admin.battery-stock-adjustments.approve', $adjustment) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-success"><i class="bi bi-check2 me-1"></i> Approve</button>
                </form>
                <form method="POST" action="{{ route('admin.battery-stock-adjustments.reject', $adjustment) }}" class="d-inline"
                      onsubmit="return confirm('Reject this adjustment?');">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-lg me-1"></i> Reject</button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card">
        <dl class="row mb-0">
            <dt class="col-3 text-muted">Reason</dt><dd class="col-9">{{ ucfirst(str_replace('_', ' ', $adjustment->reason)) }}</dd>
            <dt class="col-3 text-muted">Status</dt>
            <dd class="col-9">
                @php $st = $adjustment->status instanceof BatteryStockAdjustmentStatus ? $adjustment->status : null; @endphp
                @if($st)<span class="badge bg-{{ $st->color() }}-subtle text-{{ $st->color() }}-emphasis">{{ $st->label() }}</span>
                @else {{ $adjustment->status }} @endif
            </dd>
            <dt class="col-3 text-muted">Requested by</dt><dd class="col-9">{{ $adjustment->requester?->name ?? '—' }}</dd>
            <dt class="col-3 text-muted">Approved by</dt><dd class="col-9">{{ $adjustment->approver?->name ?? '—' }}</dd>
            <dt class="col-3 text-muted">Approved at</dt><dd class="col-9">{{ $adjustment->approved_at?->format('Y-m-d H:i') ?? '—' }}</dd>
            <dt class="col-3 text-muted">Notes</dt><dd class="col-9">{{ $adjustment->notes ?? '—' }}</dd>
        </dl>
    </div>

    <div class="admin-card mt-3">
        <h2 class="h6 mb-3">Lines</h2>
        <div class="admin-table">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Battery</th>
                        <th>Bin</th>
                        <th class="text-end">Counted qty</th>
                        <th class="text-end">Delta</th>
                        <th class="text-end">Unit cost</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($adjustment->items as $item)
                        <tr>
                            <td>
                                <a href="{{ route('admin.batteries.show', $item->battery) }}" class="text-decoration-none">
                                    {{ $item->battery?->name ?? '—' }}
                                </a>
                                @if($item->battery?->battery_code)
                                    <div class="text-muted small">{{ $item->battery->battery_code }}</div>
                                @endif
                            </td>
                            <td>{{ $item->bin?->code ?? '—' }}</td>
                            <td class="text-end">{{ number_format((float) $item->counted_quantity, 2) }}</td>
                            <td class="text-end {{ (float) $item->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="text-end">{{ $item->unit_cost !== null ? number_format((float) $item->unit_cost, 2) : '—' }}</td>
                            <td class="text-muted small">{{ $item->reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
