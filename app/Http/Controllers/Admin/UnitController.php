<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\AuditLog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');

        $units = Unit::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('short_code', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no',  fn ($qb) => $qb->where('is_active', false))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.units.index', [
            'title'  => 'Units of measure',
            'units'  => $units,
            'q'      => $q,
            'active' => $active,
        ]);
    }

    public function create(): View
    {
        return view('admin.units.create', ['title' => 'New unit']);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());
        AuditLog::record('unit.created', $unit, $unit->only(['name','short_code','decimal_precision','is_active']));

        return redirect()->route('admin.units.index')->with('status', 'Unit created.');
    }

    public function edit(Unit $unit): View
    {
        return view('admin.units.edit', [
            'title' => 'Edit unit',
            'unit'  => $unit,
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $before = $unit->only(['name','short_code','decimal_precision','is_active']);
        $unit->update($request->validated());
        AuditLog::record('unit.updated', $unit, ['before' => $before, 'after' => $unit->only(['name','short_code','decimal_precision','is_active'])]);

        return redirect()->route('admin.units.index')->with('status', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->parts()->exists() || $unit->binLocations()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete a unit that is still in use.']);
        }

        AuditLog::record('unit.deleted', $unit, $unit->only(['name','short_code']));
        $unit->delete();

        return redirect()->route('admin.units.index')->with('status', 'Unit deleted.');
    }
}