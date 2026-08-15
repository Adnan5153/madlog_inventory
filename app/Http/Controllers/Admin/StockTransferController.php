<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\BinLocation;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $service) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $transfers = StockTransfer::query()
            ->when($q !== '', fn ($qb) => $qb->where('transfer_number', 'like', "%{$q}%"))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with(['sourceBin:id,code', 'destinationBin:id,code', 'transferer:id,name', 'receiver:id,name'])
            ->withCount('items')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.stock-transfers.index', [
            'title' => 'Stock transfers',
            'transfers' => $transfers,
            'q' => $q,
            'status' => $status,
            'statuses' => [
                StockTransfer::STATUS_DRAFT,
                StockTransfer::STATUS_IN_TRANSIT,
                StockTransfer::STATUS_RECEIVED,
                StockTransfer::STATUS_CANCELLED,
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.stock-transfers.create', [
            'title' => 'New stock transfer',
            'bins' => BinLocation::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'zone']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source_bin_id' => ['nullable', 'integer', 'exists:bin_locations,id'],
            'destination_bin_id' => ['required', 'integer', 'exists:bin_locations,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.part_id' => ['required', 'integer', 'exists:parts,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.batch_number' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $t = $this->service->create(
                $request->user(),
                $request->user()->workshop_id,
                $data['source_bin_id'] ?? null,
                $data['destination_bin_id'],
                $data['notes'] ?? null,
                $data['items'],
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('admin.stock-transfers.show', $t)->with('status', 'Stock transfer saved.');
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $stockTransfer->load(['sourceBin', 'destinationBin', 'transferer', 'receiver', 'items.part']);

        return view('admin.stock-transfers.show', [
            'title' => $stockTransfer->transfer_number,
            'transfer' => $stockTransfer,
        ]);
    }

    public function dispatch(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->service->dispatch($stockTransfer, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', "Transfer {$stockTransfer->transfer_number} dispatched.");
    }

    public function receive(Request $request, StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->service->receive($stockTransfer, $request->user());
        } catch (DomainException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('status', "Transfer {$stockTransfer->transfer_number} received.");
    }
}
