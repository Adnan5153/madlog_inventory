@php
    use App\Enums\BatteryChemistry;
    use App\Enums\BatteryApplication;
    use App\Enums\BatteryCondition;
    use App\Enums\BatteryStatus;
@endphp

@extends('layouts.admin', ['title' => $battery->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Batteries', 'url' => route('admin.batteries.index')],
        ['label' => $battery->name],
    ]" />

    <x-admin.page-header :title="$battery->name" :subtitle="$battery->battery_code">
        <x-slot:actions>
            <a href="{{ route('admin.batteries.edit', $battery) }}" class="btn btn-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="admin-card">
                <h2 class="h6 mb-3">Identification</h2>
                @php
                    $chem = BatteryChemistry::tryFrom($battery->battery_type);
                    $app = BatteryApplication::tryFrom($battery->application_type);
                    $cond = BatteryCondition::tryFrom($battery->condition);
                    $stat = BatteryStatus::tryFrom($battery->status);
                @endphp
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Battery code</dt><dd class="col-8">{{ $battery->battery_code }}</dd>
                    <dt class="col-4 text-muted">SKU</dt><dd class="col-8">{{ $battery->sku ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Barcode</dt><dd class="col-8">{{ $battery->barcode ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Brand</dt><dd class="col-8">{{ $battery->brand ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Manufacturer #</dt><dd class="col-8">{{ $battery->manufacturer_part_number ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Supplier</dt><dd class="col-8">{{ $battery->supplier?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Bin</dt><dd class="col-8">{{ $battery->binLocation?->code ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Catalog state</dt><dd class="col-8"><x-admin.status-badge :on="$battery->is_active" /></dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Classification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Chemistry</dt>
                    <dd class="col-8">
                        @if($chem)<span class="badge bg-{{ $chem->color() }}-subtle text-{{ $chem->color() }}-emphasis">{{ $chem->label() }}</span>
                        @else {{ $battery->battery_type }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Application</dt>
                    <dd class="col-8">
                        @if($app)<span class="badge bg-{{ $app->color() }}-subtle text-{{ $app->color() }}-emphasis">{{ $app->label() }}</span>
                        @else — @endif
                    </dd>
                    <dt class="col-4 text-muted">Condition</dt>
                    <dd class="col-8">
                        @if($cond)<span class="badge bg-{{ $cond->color() }}-subtle text-{{ $cond->color() }}-emphasis">{{ $cond->label() }}</span>
                        @else {{ $battery->condition }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Status</dt>
                    <dd class="col-8">
                        @if($stat)<span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
                        @else {{ $battery->status }} @endif
                    </dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Technical specs</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Voltage</dt><dd class="col-8">{{ $battery->voltage !== null ? rtrim(rtrim(number_format((float) $battery->voltage, 2), '0'), '.').' V' : '—' }}</dd>
                    <dt class="col-4 text-muted">Capacity</dt><dd class="col-8">{{ $battery->capacity_ah !== null ? number_format((float) $battery->capacity_ah, 2).' Ah' : '—' }}</dd>
                    <dt class="col-4 text-muted">CCA</dt><dd class="col-8">{{ $battery->cold_cranking_amps ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Reserve capacity</dt><dd class="col-8">{{ $battery->reserve_capacity ?? '—' }} min</dd>
                    <dt class="col-4 text-muted">Terminal</dt><dd class="col-8">{{ $battery->terminal_type ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Polarity</dt><dd class="col-8">{{ $battery->polarity ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Dimensions</dt><dd class="col-8">
                        @if($battery->length_mm && $battery->width_mm && $battery->height_mm)
                            {{ number_format((float) $battery->length_mm, 0) }} × {{ number_format((float) $battery->width_mm, 0) }} × {{ number_format((float) $battery->height_mm, 0) }} mm
                        @else — @endif
                    </dd>
                    <dt class="col-4 text-muted">Weight</dt><dd class="col-8">{{ $battery->weight_kg !== null ? number_format((float) $battery->weight_kg, 2).' kg' : '—' }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Pricing & reorder</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Cost</dt><dd class="col-8">{{ number_format((float) $battery->cost_price, 2) }}</dd>
                    <dt class="col-4 text-muted">Reorder ≤</dt><dd class="col-8">{{ number_format($battery->reorder_threshold) }}</dd>
                    <dt class="col-4 text-muted">Reorder qty</dt><dd class="col-8">{{ number_format($battery->reorder_quantity) }}</dd>
                    <dt class="col-4 text-muted">On hand</dt>
                    <dd class="col-8">
                        @php $oh = (float) ($battery->on_hand ?? 0); @endphp
                        <span class="{{ $oh <= (float) $battery->reorder_threshold ? 'text-danger fw-semibold' : '' }}">{{ number_format($oh, 2) }}</span>
                    </dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Warranty</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Period</dt>
                    <dd class="col-8">{{ $battery->warranty_period_months !== null ? $battery->warranty_period_months.' months' : '—' }}</dd>
                    <dt class="col-4 text-muted">Expiry</dt>
                    <dd class="col-8">{{ $battery->warranty_expiry?->format('Y-m-d') ?? '—' }}</dd>
                </dl>
            </div>

            @if($battery->description)
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Description</h2>
                    <p class="mb-0" style="white-space: pre-line;">{{ $battery->description }}</p>
                </div>
            @endif

            @if($battery->notes)
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Internal notes</h2>
                    <p class="mb-0 text-muted" style="white-space: pre-line;">{{ $battery->notes }}</p>
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
