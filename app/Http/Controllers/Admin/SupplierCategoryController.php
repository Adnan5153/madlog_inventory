<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupplierCategoryRequest;
use App\Http\Requests\Admin\UpdateSupplierCategoryRequest;
use App\Models\AuditLog;
use App\Models\SupplierCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = SupplierCategory::query()
            ->withCount('suppliers')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.supplier-categories.index', [
            'title' => 'Supplier categories',
            'categories' => $categories,
        ]);
    }

    public function create(): View
    {
        return view('admin.supplier-categories.create', ['title' => 'New supplier category']);
    }

    public function store(StoreSupplierCategoryRequest $request): RedirectResponse
    {
        $category = SupplierCategory::create($request->validated());
        AuditLog::record('supplier_category.created', $category, $category->only(['name', 'code']));

        return redirect()->route('admin.supplier-categories.index')->with('status', 'Supplier category created.');
    }

    public function edit(SupplierCategory $supplierCategory): View
    {
        return view('admin.supplier-categories.edit', [
            'title' => 'Edit supplier category',
            'category' => $supplierCategory,
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
