@extends('layouts.admin', ['title' => 'Edit purchase order'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Purchase orders', 'url' => route('admin.purchase-orders.index')],
        ['label' => $order->po_number, 'url' => route('admin.purchase-orders.show', $order)],
        ['label' => 'Edit'],
    ]" />

    <x-admin.page-header :title="'Edit ' . $order->po_number" />

    <form method="POST" action="{{ route('admin.purchase-orders.update', $order) }}">
        @csrf
        @method('PUT')

        @include('admin.purchase-orders._form', ['order' => $order, 'suppliers' => $suppliers])

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-warning">
                <i class="bi bi-save me-1"></i> Save changes
            </button>
            <a href="{{ route('admin.purchase-orders.show', $order) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
