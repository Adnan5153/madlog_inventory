<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');

        $suppliers = Supplier::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->when($categoryId, fn ($qb) => $qb->where('supplier_category_id', $categoryId))
            ->with('category:id,name')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.suppliers.index', [
            'title' => 'Suppliers',
            'suppliers' => $suppliers,
            'q' => $q,
            'active' => $active,
            'categoryId' => $categoryId,
            'categories' => SupplierCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.suppliers.create', [
            'title' => 'New supplier',
            'categories' => SupplierCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());
        AuditLog::record('supplier.created', $supplier, $supplier->only(['name', 'email', 'phone']));

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', [
            'title' => 'Edit supplier',
            'supplier' => $supplier,
            'categories' => SupplierCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $before = $supplier->only(['name', 'email', 'phone', 'is_active']);
        $supplier->update($request->validated());
        AuditLog::record('supplier.updated', $supplier, ['before' => $before, 'after' => $supplier->only(['name', 'email', 'phone', 'is_active'])]);

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists() || $supplier->inventoryItems()->exists()) {
            return back()->withErrors(['supplier' => 'Cannot delete a supplier that is referenced by purchase orders or inventory.']);
        }

        AuditLog::record('supplier.deleted', $supplier, $supplier->only(['name']));
        $supplier->delete();

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier deleted.');
    }
}
