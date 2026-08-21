@extends('layouts.admin', ['title' => $transfer->transfer_number])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Stock transfers', 'url' => route('admin.stock-transfers.index')],
        ['label' => $transfer->transfer_number],
    ]" />

    <x-admin.page-header :title="$transfer->transfer_number" :subtitle="($transfer->sourceBin?->code ?? '—') . ' → ' . $transfer->destinationBin?->code">
        <x-slot:actions>
            @if($transfer->status === 'draft')
                <form method="POST" action="{{ route('admin.stock-transfers.dispatch', $transfer) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Dispatch
                    </button>
                </form>
            @endif
            @if($transfer->status === 'in_transit')
                <form method="POST" action="{{ route('admin.stock-transfers.receive', $transfer) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Receive
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-5 text-muted">Number</dt><dd class="col-7">{{ $transfer->transfer_number }}</dd>
                    <dt class="col-5 text-muted">Status</dt><dd class="col-7"><x-admin.status-badge :on="$transfer->status === 'received'" :label="ucfirst(str_replace('_', ' ', $transfer->status))" /></dd>
                    <dt class="col-5 text-muted">Source bin</dt><dd class="col-7">{{ $transfer->sourceBin?->code ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Destination bin</dt><dd class="col-7">{{ $transfer->destinationBin?->code ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Transferred by</dt><dd class="col-7">{{ $transfer->transferer?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Received by</dt><dd class="col-7">{{ $transfer->receiver?->name ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Dispatched at</dt><dd class="col-7">{{ $transfer->dispatched_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                    <dt class="col-5 text-muted">Received at</dt><dd class="col-7">{{ $transfer->received_at?->format('Y-m-d H:i') ?? '—' }}</dd>
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
                                <th>Part</th>
                                <th>Batch</th>
                                <th class="text-end">Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transfer->items as $line)
                                <tr>
                                    <td>{{ $line->part?->name ?? '—' }} <div class="text-muted small">{{ $line->part?->sku }}</div></td>
                                    <td>{{ $line->batch_number ?? '—' }}</td>
                                    <td class="text-end">{{ number_format($line->quantity, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
