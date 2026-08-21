@extends('layouts.admin', ['title' => $adjustment->adjustment_number])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Stock adjustments', 'url' => route('admin.stock-adjustments.index')],
        ['label' => $adjustment->adjustment_number],
    ]" />

    @if (session('status'))
        <x-admin.alert variant="success">{{ session('status') }}</x-admin.alert>
    @endif
    @error('adjustment')
        <x-admin.alert variant="danger">{{ $message }}</x-admin.alert>
    @enderror

    <x-admin.page-header :title="$adjustment->adjustment_number" :subtitle="ucfirst(str_replace('_', ' ', $adjustment->reason))">
        <x-slot:actions>
            @if($adjustment->status === 'pending')
                <form method="POST" action="{{ route('admin.stock-adjustments.approve', $adjustment) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i> Approve &amp; apply
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.stock-adjustments.reject', $adjustment) }}" class="d-inline" data-confirm-form data-confirm="Reject this adjustment?">
                    @csrf
                    <input type="hidden" name="reason" value="rejected from UI">
                    <button class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i> Reject
                    </button>
                </form>
            @else
                <span class="badge text-bg-secondary text-uppercase">{{ $adjustment->status }}</span>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Number</dt><dd class="col-7">{{ $adjustment->adjustment_number }}</dd>
                    <dt class="col-5 text-muted">Reason</dt><dd class="col-7">{{ ucfirst(str_replace('_', ' ', $adjustment->reason)) }}</dd>
                    <dt class="col-5 text-muted">Status</dt><dd class="col-7"><x-admin.status-badge :on="$adjustment->status === 'applied'" :label="ucfirst($adjustment->status)" /></dd>
                    <dt class="col-5 text-muted">Requested by</dt><dd class="col-7">{{ $adjustment->requester?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Approved by</dt><dd class="col-7">{{ $adjustment->approver?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Approved at</dt><dd class="col-7">{{ $adjustment->approved_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Applied at</dt><dd class="col-7">{{ $adjustment->applied_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="admin-card">
                <h2 class="h6 mb-3">Lines</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Part</th>
                                <th class="text-end">Before</th>
                                <th class="text-end">Delta</th>
                                <th class="text-end">After</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($adjustment->items as $line)
                                <tr>
                                    <td>#{{ $line->inventory_item_id }} <div class="text-muted small">{{ $line->inventoryItem?->bin?->code ?? '' }}</div></td>
                                    <td>{{ $line->inventoryItem?->part?->name ?? '—' }} <div class="text-muted small">{{ $line->inventoryItem?->part?->sku }}</div></td>
                                    <td class="text-end">{{ number_format($line->before_quantity, 2) }}</td>
                                    <td class="text-end @if($line->adjustment_quantity < 0) text-danger @else text-success @endif">{{ ($line->adjustment_quantity >= 0 ? '+' : '') . number_format($line->adjustment_quantity, 2) }}</td>
                                    <td class="text-end">{{ number_format($line->after_quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
