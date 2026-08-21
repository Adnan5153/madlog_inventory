<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierCategoryRequest;
use App\Http\Requests\Admin\UpdateSupplierCategoryRequest;
use App\Models\AuditLog;
use App\Models\SupplierCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierCategoryController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $categories = $this->buildSupplierCategoriesQuery()
            ->paginate(20)
            ->withQueryString();

        return view('admin.supplier-categories.index', [
            'title' => 'Supplier categories',
            'supplierCategories' => $categories,
        ]);
    }

    /**
     * Live-search JSON endpoint for the supplier categories index.
     */
    public function search(Request $request): JsonResponse
    {
        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.supplier-categories._row-template',
            singular: 'supplierCategory',
            builder: fn () => $this->buildSupplierCategoriesQuery(),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildSupplierCategoriesQuery()
    {
        return SupplierCategory::query()
            ->withCount('suppliers')
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over
     * `$supplierCategories`.
     */
    protected function singularNoun(): string
    {
        return 'supplierCategory';
    }

    public function create(): View
    {
        return view('admin.supplier-categories.create', [
            'title' => 'New supplier category',
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StoreSupplierCategoryRequest $request): RedirectResponse
    {
        $category = SupplierCategory::create($request->validated());
        AuditLog::record('supplier_category.created', $category, $category->only(['name', 'code', 'workshop_id']));

        return redirect()->route('admin.supplier-categories.index')->with('status', 'Supplier category created.');
    }

    public function edit(SupplierCategory $supplierCategory): View
    {
        return view('admin.supplier-categories.edit', [
            'title' => 'Edit supplier category',
            'category' => $supplierCategory,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function update(UpdateSupplierCategoryRequest $request, SupplierCategory $supplierCategory): RedirectResponse
    {
        $before = $supplierCategory->only(['name', 'code', 'is_active']);
        $supplierCategory->update($request->validated());
        AuditLog::record('supplier_category.updated', $supplierCategory, ['before' => $before, 'after' => $supplierCategory->only(['name', 'code', 'is_active'])]);

        return redirect()->route('admin.supplier-categories.index')->with('status', 'Supplier category updated.');
    }

    public function destroy(SupplierCategory $supplierCategory): RedirectResponse
    {
        if ($supplierCategory->suppliers()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category that still has suppliers.']);
        }

        AuditLog::record('supplier_category.deleted', $supplierCategory, $supplierCategory->only(['name']));
        $supplierCategory->delete();

        return redirect()->route('admin.supplier-categories.index')->with('status', 'Supplier category deleted.');
    }
}
