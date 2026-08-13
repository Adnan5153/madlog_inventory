@extends('layouts.admin', ['title' => 'New stock transfer'])

@section('content')
    <x-admin.breadcrumb :items="[
        ['label' => 'Stock transfers', 'url' => route('admin.stock-transfers.index')],
        ['label' => 'New'],
    ]" />

    <x-admin.page-header title="New stock transfer" subtitle="Move stock between bins. Dispatch decrements the source, receive increments the destination." />

    <form method="POST" action="{{ route('admin.stock-transfers.store') }}">
        @csrf

        <div class="admin-card">
            <div class="row g-3">
                <div class="col-md-5">
                    <label for="source_bin_id" class="form-label">Source bin (optional)</label>
                    <input type="number" id="source_bin_id" name="source_bin_id" class="form-control" value="{{ old('source_bin_id') }}" placeholder="Bin ID">
                    @error('source_bin_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-5">
                    <label for="destination_bin_id" class="form-label">Destination bin</label>
                    <input type="number" id="destination_bin_id" name="destination_bin_id" class="form-control" value="{{ old('destination_bin_id') }}" placeholder="Bin ID" required>
                    @error('destination_bin_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2 align-self-end">
                    <span class="text-muted small">Available bins: {{ $bins->count() }}</span>
                </div>
                <div class="col-12">
                    <label for="notes" class="form-label">Notes</label>
                    <input type="text" id="notes" name="notes" class="form-control" value="{{ old('notes') }}">
                    @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="admin-card mt-3">
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
                    <tbody id="trf-lines">
                        <tr>
                            <td>
                                <input type="number" name="items[0][part_id]" class="form-control form-control-sm" placeholder="Part ID" required>
                                @error('items.0.part_id')<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" name="items[0][batch_number]" class="form-control form-control-sm">
                                @error('items.0.batch_number')<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0.01" name="items[0][quantity]" class="form-control form-control-sm text-end" required>
                                @error('items.0.quantity')<div class="text-danger small">{{ $message }}</div>@enderror
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="button" id="trf-add" class="btn btn-outline-secondary btn-sm mt-2">
                <i class="bi bi-plus-lg me-1"></i> Add line
            </button>
        </div>

        <div class="mt-3 d-flex gap-2">
            <button class="btn btn-warning">
                <i class="bi bi-save me-1"></i> Save draft
            </button>
            <a href="{{ route('admin.stock-transfers.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <script>
        (function () {
            const tbody = document.getElementById('trf-lines');
            const add = document.getElementById('trf-add');
            if (!tbody || !add) return;
            add.addEventListener('click', () => {
                const idx = tbody.querySelectorAll('tr').length;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="number" name="items[${idx}][part_id]" class="form-control form-control-sm" placeholder="Part ID" required></td>
                    <td><input type="text" name="items[${idx}][batch_number]" class="form-control form-control-sm"></td>
                    <td><input type="number" step="0.01" min="0.01" name="items[${idx}][quantity]" class="form-control form-control-sm text-end" required></td>
                `;
                tbody.appendChild(tr);
            });
        })();
    </script>
@endsection
