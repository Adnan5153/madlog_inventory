@extends('layouts.admin', ['title' => 'New purchase order'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Purchase orders', 'url' => route('admin.purchase-orders.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New purchase order" subtitle="Draft saved immediately. Submit from the detail view to send for approval." />

    <form method="POST" action="{{ route('admin.purchase-orders.store') }}">
        @csrf

        @include('admin.purchase-orders._form', ['order' => null, 'suppliers' => $suppliers, 'workshops' => $workshops])

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary">
                <i class="bi bi-save me-1"></i> Create draft
            </button>
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
@endsection
