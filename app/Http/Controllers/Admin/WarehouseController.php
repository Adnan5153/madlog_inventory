<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkshopRequest;
use App\Http\Requests\Admin\UpdateWorkshopRequest;
use App\Models\AuditLog;
use App\Models\Workshop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');

        $warehouses = Workshop::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('slug', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->withCount(['binLocations', 'parts', 'suppliers', 'users'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.warehouses.index', [
            'title'      => 'Warehouses',
            'warehouses' => $warehouses,
            'q'          => $q,
            'active'     => $active,
            'user'       => $request->user(),
        ]);
    }

    public function create(): View
    {
        return view('admin.warehouses.create', ['title' => 'New warehouse']);
    }

    public function store(StoreWorkshopRequest $request): RedirectResponse
    {
        $workshop = Workshop::create($request->validated());
        AuditLog::record('workshop.created', $workshop, $workshop->only(['name', 'slug']));

        return redirect()->route('admin.warehouses.index')->with('status', 'Warehouse created.');
    }

    public function show(Workshop $warehouse): View
    {
        $warehouse->loadCount(['binLocations', 'parts', 'suppliers', 'users']);
        $warehouse->load(['binLocations' => fn ($q) => $q->orderBy('code')->limit(50)]);

        return view('admin.warehouses.show', [
            'title'     => $warehouse->name,
            'warehouse' => $warehouse,
        ]);
    }

    public function edit(Workshop $warehouse): View
    {
        return view('admin.warehouses.edit', [
            'title'     => 'Edit warehouse',
            'warehouse' => $warehouse,
        ]);
    }

    public function update(UpdateWorkshopRequest $request, Workshop $warehouse): RedirectResponse
    {
        $before = $warehouse->only(['name', 'slug', 'is_active']);
        $warehouse->update($request->validated());
        AuditLog::record('workshop.updated', $warehouse, ['before' => $before, 'after' => $warehouse->only(['name', 'slug', 'is_active'])]);

        return redirect()->route('admin.warehouses.index')->with('status', 'Warehouse updated.');
    }

    public function destroy(Workshop $warehouse): RedirectResponse
    {
        // Soft-delete: don't actually destroy rows; the cascade on
        // dependents still applies to in-memory queries but the rows
        // remain queryable for audit and reporting.
        AuditLog::record('workshop.deleted', $warehouse, $warehouse->only(['name']));
        $warehouse->delete();

        return redirect()->route('admin.warehouses.index')->with('status', 'Warehouse archived.');
    }
}