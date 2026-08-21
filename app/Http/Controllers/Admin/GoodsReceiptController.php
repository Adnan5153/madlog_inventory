<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    use HasLiveSearch;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $receipts = $this->buildGoodsReceiptsQuery($q, $status)
            ->paginate(20)
            ->withQueryString();

        return view('admin.goods-receipts.index', [
            'title' => 'Goods receipts',
            'receipts' => $receipts,
            'q' => $q,
            'status' => $status,
            'statuses' => [
                GoodsReceipt::STATUS_RECEIVED,
                GoodsReceipt::STATUS_PARTIAL,
                GoodsReceipt::STATUS_DISPUTED,
            ],
        ]);
    }

    /**
     * Live-search JSON endpoint for the goods receipts index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.goods-receipts._row-template',
            singular: 'receipt',
            builder: fn () => $this->buildGoodsReceiptsQuery($q, $status),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildGoodsReceiptsQuery(string $q, mixed $status)
    {
        return GoodsReceipt::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('grn_number', 'like', "%{$q}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with(['purchaseOrder:id,po_number,supplier_id', 'purchaseOrder.supplier:id,name', 'receiver:id,name'])
            ->withCount('items')
            ->latest('received_at');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$receipts`.
     */
    protected function singularNoun(): string
    {
        return 'receipt';
    }

    public function show(GoodsReceipt $goodsReceipt): View
    {
        $goodsReceipt->load([
            'purchaseOrder.supplier',
            'purchaseOrder.items.part',
            'receiver',
            'binLocation',
            'items.part',
            'items.binLocation',
        ]);

        return view('admin.goods-receipts.show', [
            'title' => $goodsReceipt->grn_number,
            'receipt' => $goodsReceipt,
        ]);
    }
}
