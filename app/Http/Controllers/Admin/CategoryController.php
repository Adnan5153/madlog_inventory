<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartCategoryRequest;
use App\Http\Requests\Admin\UpdatePartCategoryRequest;
use App\Models\AuditLog;
use App\Models\PartCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categories = PartCategory::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->withCount('parts')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'title'      => 'Categories',
            'categories' => $categories,
            'q'          => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.create', ['title' => 'New category']);
    }

    public function store(StorePartCategoryRequest $request): RedirectResponse
    {
        $category = PartCategory::create($request->validated());
        AuditLog::record('category.created', $category, $category->only(['name','slug','description']));

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(PartCategory $category): View
    {
        return view('admin.categories.edit', [
            'title'    => 'Edit category',
            'category' => $category,
        ]);
    }

    public function update(UpdatePartCategoryRequest $request, PartCategory $category): RedirectResponse
    {
        $before = $category->only(['name','slug','description']);
        $category->update($request->validated());
        AuditLog::record('category.updated', $category, ['before' => $before, 'after' => $category->only(['name','slug','description'])]);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(PartCategory $category): RedirectResponse
    {
        if ($category->parts()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category that is still in use by parts.']);
        }

        AuditLog::record('category.deleted', $category, $category->only(['name','slug']));
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }
}