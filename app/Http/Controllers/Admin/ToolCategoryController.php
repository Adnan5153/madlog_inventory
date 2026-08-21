<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreToolCategoryRequest;
use App\Http\Requests\Admin\UpdateToolCategoryRequest;
use App\Models\AuditLog;
use App\Models\ToolCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ToolCategoryController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $categories = $this->buildCategoriesQuery($request, $q)
            ->paginate(20)
            ->withQueryString();

        return view('admin.tool-categories.index', [
            'title' => 'Tool categories',
            'categories' => $categories,
            'q' => $q,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.tool-categories._row-template',
            singular: 'category',
            builder: fn () => $this->buildCategoriesQuery($request, $q),
        );
    }

    protected function singularNoun(): string
    {
        return 'category';
    }

    private function buildCategoriesQuery(Request $request, string $q): Builder
    {
        return ToolCategory::query()
            ->when($q !== '', function (Builder $qb) use ($q) {
                $like = '%'.$q.'%';
                $qb->where(function (Builder $w) use ($like) {
                    $w->where('tool_categories.name', 'like', $like)
                        ->orWhere('tool_categories.slug', 'like', $like)
                        ->orWhere('tool_categories.description', 'like', $like);
                });
            })
            ->orderBy('tool_categories.name')
            ->orderBy('tool_categories.id');
    }

    public function create(Request $request): View
    {
        return view('admin.tool-categories.create', [
            'title' => 'New tool category',
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $this->selectedWorkshopId($request) ?? auth()->user()?->workshop_id,
        ]);
    }

    public function store(StoreToolCategoryRequest $request): RedirectResponse
    {
        $category = ToolCategory::create($request->validated());
        AuditLog::record('tool_category.created', $category, $category->only(['name', 'workshop_id']));

        return redirect()->route('admin.tool-categories.index')->with('status', 'Tool category created.');
    }

    public function show(ToolCategory $toolCategory): View
    {
        $toolCategory->loadCount('tools');

        return view('admin.tool-categories.show', [
            'title' => $toolCategory->name,
            'category' => $toolCategory,
        ]);
    }

    public function edit(ToolCategory $toolCategory): View
    {
        return view('admin.tool-categories.edit', [
            'title' => 'Edit tool category',
            'category' => $toolCategory,
            'workshops' => $this->workshopsForForm(),
            'pickedWorkshopId' => $toolCategory->workshop_id,
        ]);
    }

    public function update(UpdateToolCategoryRequest $request, ToolCategory $toolCategory): RedirectResponse
    {
        $before = $toolCategory->only(['name', 'is_active']);
        $toolCategory->update($request->validated());
        AuditLog::record('tool_category.updated', $toolCategory, [
            'before' => $before,
            'after' => $toolCategory->only(['name', 'is_active']),
        ]);

        return redirect()->route('admin.tool-categories.index')->with('status', 'Tool category updated.');
    }

    public function destroy(ToolCategory $toolCategory): RedirectResponse
    {
        if ($toolCategory->tools()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that has tools assigned to it. Reassign tools first.',
            ]);
        }

        AuditLog::record('tool_category.deleted', $toolCategory, $toolCategory->only(['name']));
        $toolCategory->delete();

        return redirect()->route('admin.tool-categories.index')->with('status', 'Tool category deleted.');
    }
}
