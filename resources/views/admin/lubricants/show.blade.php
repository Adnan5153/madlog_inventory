@php
    use App\Enums\LubricantApplication;
    use App\Enums\LubricantStatus;
    use App\Enums\LubricantType;
    use App\Enums\LubricantViscosity;
@endphp

@extends('layouts.admin', ['title' => $lubricant->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Lubricants', 'url' => route('admin.lubricants.index')],
        ['label' => $lubricant->name],
    ]" />

    <x-admin.page-header :title="$lubricant->name" :subtitle="$lubricant->lubricant_code">
        <x-slot:actions>
            <a href="{{ route('admin.lubricants.edit', $lubricant) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                @php
                    $type = LubricantType::tryFrom($lubricant->lubricant_type);
                    $viscosity = LubricantViscosity::tryFrom($lubricant->viscosity_grade);
                    $application = LubricantApplication::tryFrom($lubricant->application_type);
                    $stat = LubricantStatus::tryFrom($lubricant->status);
                @endphp
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Lubricant code</dt><dd class="col-8">{{ $lubricant->lubricant_code }}</dd>
                    <dt class="col-4 text-muted">SKU</dt><dd class="col-8">{{ $lubricant->sku ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Barcode</dt><dd class="col-8">{{ $lubricant->barcode ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Brand</dt><dd class="col-8">{{ $lubricant->brand ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Manufacturer</dt><dd class="col-8">{{ $lubricant->manufacturer ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Manufacturer #</dt><dd class="col-8">{{ $lubricant->manufacturer_part_number ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Supplier</dt><dd class="col-8">{{ $lubricant->supplier?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Bin</dt><dd class="col-8">{{ $lubricant->binLocation?->code ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Catalog state</dt><dd class="col-8"><x-admin.status-badge :on="$lubricant->is_active" /></dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Classification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Type (base)</dt>
                    <dd class="col-8">
                        @if($type)<span class="badge bg-{{ $type->color() }}-subtle text-{{ $type->color() }}-emphasis">{{ $type->label() }}</span>
                        @else {{ $lubricant->lubricant_type }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Viscosity</dt>
                    <dd class="col-8">
                        @if($viscosity)<span class="badge bg-{{ $viscosity->color() }}-subtle text-{{ $viscosity->color() }}-emphasis">{{ $viscosity->label() }}</span>
                        @else — @endif
                    </dd>
                    <dt class="col-4 text-muted">Application</dt>
                    <dd class="col-8">
                        @if($application)<span class="badge bg-{{ $application->color() }}-subtle text-{{ $application->color() }}-emphasis">{{ $application->label() }}</span>
                        @else — @endif
                    </dd>
                    <dt class="col-4 text-muted">Status</dt>
                    <dd class="col-8">
                        @if($stat)<span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
                        @else {{ $lubricant->status }} @endif
                    </dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Technical specs</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">OEM specification</dt><dd class="col-8">{{ $lubricant->oem_specification ?? '—' }}</dd>
                    <dt class="col-4 text-muted">ACEA</dt><dd class="col-8">{{ $lubricant->acea_specification ?? '—' }}</dd>
                    <dt class="col-4 text-muted">API</dt><dd class="col-8">{{ $lubricant->api_specification ?? '—' }}</dd>
                    <dt class="col-4 text-muted">ISO grade</dt><dd class="col-8">{{ $lubricant->iso_grade ?? '—' }}</dd>
                    <dt class="col-4 text-muted">NLGI grade</dt><dd class="col-8">{{ $lubricant->nlgi_grade ?? '—' }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Packaging</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Type</dt><dd class="col-8">
                        @php
                            $pkg = \App\Enums\LubricantPackageType::tryFrom($lubricant->package_type);
                        @endphp
                        @if($pkg) {{ $pkg->label() }} @else {{ $lubricant->package_type }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Size</dt><dd class="col-8">{{ number_format((float) $lubricant->package_size, 2) }} {{ $lubricant->package_unit }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Pricing & reorder</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Cost</dt><dd class="col-8">{{ number_format((float) $lubricant->cost_price, 2) }}</dd>
                    <dt class="col-4 text-muted">Reorder ≤</dt><dd class="col-8">{{ number_format($lubricant->reorder_threshold) }}</dd>
                    <dt class="col-4 text-muted">Reorder qty</dt><dd class="col-8">{{ number_format($lubricant->reorder_quantity) }}</dd>
                    <dt class="col-4 text-muted">On hand</dt>
                    <dd class="col-8">
                        @php $oh = (float) ($lubricant->on_hand ?? 0); @endphp
                        <span class="{{ $oh <= (float) $lubricant->reorder_threshold ? 'text-danger fw-semibold' : '' }}">{{ number_format($oh, 2) }}</span>
                    </dd>
                </dl>
            </div>

            @if($lubricant->description)
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Description</h2>
                    <p class="mb-0" style="white-space: pre-line;">{{ $lubricant->description }}</p>
                </div>
            @endif

            @if($lubricant->notes)
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Internal notes</h2>
                    <p class="mb-0 text-muted" style="white-space: pre-line;">{{ $lubricant->notes }}</p>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            <div class="admin-card">
                <h2 class="h6 mb-3">Recent stock movements</h2>
                <div class="admin-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Type</th>
                                <th>Bin</th>
                                <th class="text-end">Quantity</th>
                                <th>By</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($movements as $m)
                                <tr>
                                    <td class="text-muted small">{{ $m->occurred_at?->format('Y-m-d H:i') ?? $m->created_at->format('Y-m-d H:i') }}</td>
                                    <td><x-admin.status-badge variant="info">{{ $m->type?->label() ?? $m->type }}</x-admin.status-badge></td>
                                    <td>{{ $m->bin?->code ?? '—' }}</td>
                                    <td class="text-end {{ (float) $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ number_format((float) $m->quantity, 2) }}</td>
                                    <td class="text-muted">{{ $m->user?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5"><x-admin.empty-state icon="bi-arrow-left-right" title="No movements yet" /></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection