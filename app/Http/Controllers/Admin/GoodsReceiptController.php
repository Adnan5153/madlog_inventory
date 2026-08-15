<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $receipts = GoodsReceipt::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('grn_number', 'like', "%{$q}%")
                    ->orWhere('supplier_invoice_number', 'like', "%{$q}%");
            }))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->with(['purchaseOrder:id,po_number,supplier_id', 'purchaseOrder.supplier:id,name', 'receiver:id,name'])
            ->withCount('items')
            ->latest('received_at')
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
