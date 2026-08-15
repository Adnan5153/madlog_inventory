<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\AuditLog;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $brands = Brand::query()
            ->when($q !== '', fn ($qb) => $qb->where('name', 'like', "%{$q}%"))
            ->withCount('parts')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.brands.index', [
            'title' => 'Brands',
            'brands' => $brands,
            'q' => $q,
        ]);
    }

    public function create(): View
    {
        return view('admin.brands.create', ['title' => 'New brand']);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $brand = Brand::create($request->validated());
        AuditLog::record('brand.created', $brand, $brand->only(['name', 'slug']));

        return redirect()->route('admin.brands.index')->with('status', 'Brand created.');
    }

    public function edit(Brand $brand): View
    {
        return view('admin.brands.edit', [
            'title' => 'Edit brand',
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $before = $brand->only(['name', 'slug']);
        $brand->update($request->validated());
        AuditLog::record('brand.updated', $brand, ['before' => $before, 'after' => $brand->only(['name', 'slug'])]);

        return redirect()->route('admin.brands.index')->with('status', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->parts()->exists()) {
            return back()->withErrors(['brand' => 'Cannot delete a brand that is still in use by parts.']);
        }

        AuditLog::record('brand.deleted', $brand, $brand->only(['name', 'slug']));
        $brand->delete();

        return redirect()->route('admin.brands.index')->with('status', 'Brand deleted.');
    }
}
