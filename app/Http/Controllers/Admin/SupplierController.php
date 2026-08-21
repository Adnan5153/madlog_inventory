<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierRequest;
use App\Http\Requests\Admin\UpdateSupplierRequest;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');

        $suppliers = $this->buildSuppliersQuery($q, $active, $categoryId)
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

    /**
     * Live-search JSON endpoint for the suppliers index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $categoryId = $request->query('category_id');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.suppliers._row-template',
            singular: 'supplier',
            builder: fn () => $this->buildSuppliersQuery($q, $active, $categoryId),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildSuppliersQuery(string $q, ?string $active, ?string $categoryId)
    {
        return Supplier::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->when($categoryId, fn ($qb) => $qb->where('supplier_category_id', $categoryId))
            ->with('category:id,name')
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$suppliers`.
     */
    protected function singularNoun(): string
    {
        return 'supplier';
    }

    public function create(): View
    {
        return view('admin.suppliers.create', [
            'title' => 'New supplier',
            'categories' => SupplierCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());
        AuditLog::record('supplier.created', $supplier, $supplier->only(['name', 'email', 'phone', 'workshop_id']));

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier created.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', [
            'title' => 'Edit supplier',
            'supplier' => $supplier,
            'categories' => SupplierCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'workshops' => $this->workshopsForForm(),
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
