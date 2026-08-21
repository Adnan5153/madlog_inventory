<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartCategoryRequest;
use App\Http\Requests\Admin\UpdatePartCategoryRequest;
use App\Models\AuditLog;
use App\Models\PartCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $categories = $this->buildCategoriesQuery($q)
            ->paginate(20)
            ->withQueryString();

        return view('admin.categories.index', [
            'title' => 'Categories',
            'categories' => $categories,
            'q' => $q,
        ]);
    }

    /**
     * Live-search JSON endpoint for the categories index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.categories._row-template',
            singular: 'category',
            builder: fn () => $this->buildCategoriesQuery($q),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildCategoriesQuery(string $q)
    {
        return PartCategory::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->withCount('parts')
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$categories`.
     */
    protected function singularNoun(): string
    {
        return 'category';
    }

    public function create(): View
    {
        return view('admin.categories.create', [
            'title' => 'New category',
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StorePartCategoryRequest $request): RedirectResponse
    {
        $category = PartCategory::create($request->validated());
        AuditLog::record('category.created', $category, $category->only(['name', 'slug', 'description', 'workshop_id']));

        return redirect()->route('admin.categories.index')->with('status', 'Category created.');
    }

    public function edit(PartCategory $category): View
    {
        return view('admin.categories.edit', [
            'title' => 'Edit category',
            'category' => $category,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function update(UpdatePartCategoryRequest $request, PartCategory $category): RedirectResponse
    {
        $before = $category->only(['name', 'slug', 'description']);
        $category->update($request->validated());
        AuditLog::record('category.updated', $category, ['before' => $before, 'after' => $category->only(['name', 'slug', 'description'])]);

        return redirect()->route('admin.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(PartCategory $category): RedirectResponse
    {
        if ($category->parts()->exists()) {
            return back()->withErrors(['category' => 'Cannot delete a category that is still in use by parts.']);
        }

        AuditLog::record('category.deleted', $category, $category->only(['name', 'slug']));
        $category->delete();

        return redirect()->route('admin.categories.index')->with('status', 'Category deleted.');
    }
}
