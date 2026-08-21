@php
    use App\Enums\ToolCheckoutStatus;
    use App\Enums\ToolCondition;
    use App\Enums\ToolStatus;
    use Illuminate\Support\Carbon;
@endphp

@extends('layouts.admin', ['title' => $tool->name])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Tools', 'url' => route('admin.tools.index')],
        ['label' => $tool->name],
    ]" />

    <x-admin.page-header
        :title="$tool->name"
        :subtitle="$tool->tool_code . ' · ' . ($tool->brand ?? 'No brand') . ($tool->model ? ' · '.$tool->model : '')">
        <x-slot:actions>
            <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to tools
            </a>
            @can('update', $tool)
                <a href="{{ route('admin.tools.edit', $tool) }}" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
        </x-slot:actions>
    </x-admin.page-header>

    @php
        $stat = $tool->status instanceof ToolStatus ? $tool->status : null;
        $cond = $tool->condition instanceof ToolCondition ? $tool->condition : null;
        $lastMaint = $tool->lastMaintenanceAt();
        $nextDue = $tool->nextMaintenanceDueAt();
        $nextDueOverdue = $tool->isMaintenanceOverdue();
    @endphp

    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="admin-card">
                <h2 class="h6 mb-3">Current state</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Status</dt>
                    <dd class="col-8">
                        @if($stat)
                            <span class="badge bg-{{ $stat->color() }}-subtle text-{{ $stat->color() }}-emphasis">{{ $stat->label() }}</span>
                        @else {{ $tool->status }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Condition</dt>
                    <dd class="col-8">
                        @if($cond)
                            <span class="badge bg-{{ $cond->color() }}-subtle text-{{ $cond->color() }}-emphasis">{{ $cond->label() }}</span>
                        @else {{ $tool->condition }} @endif
                    </dd>
                    <dt class="col-4 text-muted">Holder</dt>
                    <dd class="col-8">{{ $tool->currentHolder?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Bin</dt>
                    <dd class="col-8">{{ $tool->binLocation?->code ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Last maintenance</dt>
                    <dd class="col-8">{{ $lastMaint?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Next due</dt>
                    <dd class="col-8">
                        @if($nextDue)
                            <span class="{{ $nextDueOverdue ? 'text-danger fw-semibold' : '' }}">
                                {{ $nextDue->format('Y-m-d') }}
                                @if($nextDueOverdue)<i class="bi bi-exclamation-triangle-fill ms-1"></i>@endif
                            </span>
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-4 text-muted">Active</dt>
                    <dd class="col-8"><x-admin.status-badge :on="$tool->is_active" /></dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Identification</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Tool code</dt><dd class="col-8">{{ $tool->tool_code }}</dd>
                    <dt class="col-4 text-muted">Brand</dt><dd class="col-8">{{ $tool->brand ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Model</dt><dd class="col-8">{{ $tool->model ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Serial #</dt><dd class="col-8">{{ $tool->serial_number ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Barcode</dt><dd class="col-8">{{ $tool->barcode ?? '—' }}</dd>
                    <dt class="col-4 text-muted">QR code</dt><dd class="col-8">{{ $tool->qr_code ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Category</dt><dd class="col-8">{{ $tool->category?->name ?? '—' }}</dd>
                </dl>
            </div>

            <div class="admin-card mt-3">
                <h2 class="h6 mb-3">Acquisition</h2>
                <dl class="row mb-0">
                    <dt class="col-4 text-muted">Supplier</dt><dd class="col-8">{{ $tool->supplier?->name ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Purchase date</dt><dd class="col-8">{{ $tool->purchase_date?->format('Y-m-d') ?? '—' }}</dd>
                    <dt class="col-4 text-muted">Purchase price</dt><dd class="col-8">{{ $tool->purchase_price !== null ? '$'.number_format((float) $tool->purchase_price, 2) : '—' }}</dd>
                    <dt class="col-4 text-muted">Warranty expiry</dt><dd class="col-8">{{ $tool->warranty_expiry?->format('Y-m-d') ?? '—' }}</dd>
                </dl>
            </div>

            @if($tool->notes)
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Notes</h2>
                    <p class="mb-0">{{ $tool->notes }}</p>
                </div>
            @endif
        </div>

        <div class="col-12 col-lg-7">
            <div class="admin-card">
                <h2 class="h6 mb-3">Workflow</h2>
                <div class="d-flex flex-wrap gap-2">
                    @can('checkout', $tool)
                        @if($tool->status === ToolStatus::Available)
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checkoutModal">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Check out
                            </button>
                        @endif
                    @endcan

                    @can('checkin', $tool)
                        @if($tool->status === ToolStatus::CheckedOut && $currentCheckout)
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#checkinModal">
                                <i class="bi bi-box-arrow-in-down-left me-1"></i> Check in
                            </button>
                        @endif
                    @endcan

                    <a href="{{ route('admin.tool-maintenance.index', $tool) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-wrench me-1"></i> Maintenance history
                    </a>

                    @can('create', \App\Models\ToolMaintenanceRecord::class)
                        <a href="{{ route('admin.tool-maintenance.create', $tool) }}" class="btn btn-outline-primary">
                            <i class="bi bi-plus-lg me-1"></i> Record maintenance
                        </a>
                    @endcan
                </div>

                @if($currentCheckout)
                    <hr class="my-3">
                    <h3 class="h6">Current checkout</h3>
                    @php
                        $co = $currentCheckout;
                        $coStat = $co->status instanceof ToolCheckoutStatus ? $co->status : null;
                        $isOverdue = $co->expected_return_at && $co->expected_return_at->isPast();
                    @endphp
                    <dl class="row mb-0">
                        <dt class="col-4 text-muted">Holder</dt>
                        <dd class="col-8">{{ $co->user?->name ?? '—' }}</dd>
                        <dt class="col-4 text-muted">Issued by</dt>
                        <dd class="col-8">{{ $co->issuedBy?->name ?? '—' }}</dd>
                        <dt class="col-4 text-muted">Checked out</dt>
                        <dd class="col-8">{{ $co->checked_out_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        <dt class="col-4 text-muted">Expected return</dt>
                        <dd class="col-8 {{ $isOverdue ? 'text-danger fw-semibold' : '' }}">
                            {{ $co->expected_return_at?->format('Y-m-d H:i') ?? '—' }}
                            @if($isOverdue)<i class="bi bi-exclamation-triangle-fill ms-1"></i>@endif
                        </dd>
                        <dt class="col-4 text-muted">Purpose</dt>
                        <dd class="col-8">{{ $co->purpose ?? '—' }}</dd>
                        <dt class="col-4 text-muted">Status</dt>
                        <dd class="col-8">
                            @if($coStat)
                                <span class="badge bg-{{ $coStat->color() }}-subtle text-{{ $coStat->color() }}-emphasis">{{ $coStat->label() }}</span>
                            @else {{ $co->status }} @endif
                        </dd>
                    </dl>
                @endif
            </div>

            @if($tool->checkouts && $tool->checkouts->count())
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Checkout history</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Out</th>
                                    <th>Returned</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tool->checkouts as $co)
                                    @php $coStat = $co->status instanceof ToolCheckoutStatus ? $co->status : null; @endphp
                                    <tr>
                                        <td>{{ $co->user?->name ?? '—' }}</td>
                                        <td class="text-nowrap">{{ $co->checked_out_at?->format('Y-m-d') ?? '—' }}</td>
                                        <td class="text-nowrap">{{ $co->returned_at?->format('Y-m-d') ?? '—' }}</td>
                                        <td>
                                            @if($coStat)
                                                <span class="badge bg-{{ $coStat->color() }}-subtle text-{{ $coStat->color() }}-emphasis">{{ $coStat->label() }}</span>
                                            @else {{ $co->status }} @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($tool->maintenanceRecords && $tool->maintenanceRecords->count())
                <div class="admin-card mt-3">
                    <h2 class="h6 mb-3">Maintenance history</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Performed</th>
                                    <th>By</th>
                                    <th>Vendor</th>
                                    <th>Cost</th>
                                    <th>Next due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tool->maintenanceRecords as $m)
                                    <tr>
                                        <td>{{ $m->type->label() }}</td>
                                        <td class="text-nowrap">{{ $m->performed_at?->format('Y-m-d') ?? '—' }}</td>
                                        <td>{{ $m->performedBy?->name ?? '—' }}</td>
                                        <td>{{ $m->vendor ?? '—' }}</td>
                                        <td>{{ $m->cost !== null ? '$'.number_format((float) $m->cost, 2) : '—' }}</td>
                                        <td>{{ $m->next_due_at?->format('Y-m-d') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @can('checkout', $tool)
        @if($tool->status === ToolStatus::Available)
            <div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.tools.checkout', $tool) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Check out tool</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="co_user_id" class="form-label">User <span class="text-danger">*</span></label>
                                <select id="co_user_id" name="user_id" required class="form-select">
                                    <option value="">— Select user —</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="co_expected_return_at" class="form-label">Expected return</label>
                                <input id="co_expected_return_at" type="datetime-local" name="expected_return_at"
                                       class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="co_purpose" class="form-label">Purpose</label>
                                <input id="co_purpose" type="text" name="purpose" maxlength="255"
                                       class="form-control" placeholder="e.g. Job #1042">
                            </div>
                            <div class="mb-3">
                                <label for="co_notes" class="form-label">Notes</label>
                                <textarea id="co_notes" name="notes" rows="2" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Check out</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan

    @can('checkin', $tool)
        @if($tool->status === ToolStatus::CheckedOut && $currentCheckout)
            <div class="modal fade" id="checkinModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('admin.tools.checkin', $tool) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Check in tool</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="ci_received_by" class="form-label">Received by <span class="text-danger">*</span></label>
                                <select id="ci_received_by" name="received_by" required class="form-select">
                                    <option value="">— Select user —</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}" @selected(auth()->id() === $u->id)>{{ $u->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="ci_condition" class="form-label">Condition at return <span class="text-danger">*</span></label>
                                <select id="ci_condition" name="condition_at_return" required class="form-select">
                                    @foreach(ToolCondition::cases() as $c)
                                        <option value="{{ $c->value }}" @selected(old('condition_at_return', $tool->condition) === $c->value)>{{ $c->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="ci_notes" class="form-label">Notes</label>
                                <textarea id="ci_notes" name="notes" rows="2" class="form-control"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Check in</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @endcan
@endsection
