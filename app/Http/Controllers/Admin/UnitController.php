<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\AuditLog;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnitController extends Controller
{
    use HasLiveSearch;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');

        $units = $this->buildUnitsQuery($q, $active)
            ->paginate(20)
            ->withQueryString();

        return view('admin.units.index', [
            'title' => 'Units of measure',
            'units' => $units,
            'q' => $q,
            'active' => $active,
        ]);
    }

    /**
     * Live-search JSON endpoint for the units index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.units._row-template',
            singular: 'unit',
            builder: fn () => $this->buildUnitsQuery($q, $active),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildUnitsQuery(string $q, ?string $active)
    {
        return Unit::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('short_code', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->orderBy('name');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$units`.
     */
    protected function singularNoun(): string
    {
        return 'unit';
    }

    public function create(): View
    {
        return view('admin.units.create', ['title' => 'New unit']);
    }

    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $unit = Unit::create($request->validated());
        AuditLog::record('unit.created', $unit, $unit->only(['name', 'short_code', 'decimal_precision', 'is_active']));

        return redirect()->route('admin.units.index')->with('status', 'Unit created.');
    }

    public function edit(Unit $unit): View
    {
        return view('admin.units.edit', [
            'title' => 'Edit unit',
            'unit' => $unit,
        ]);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $before = $unit->only(['name', 'short_code', 'decimal_precision', 'is_active']);
        $unit->update($request->validated());
        AuditLog::record('unit.updated', $unit, ['before' => $before, 'after' => $unit->only(['name', 'short_code', 'decimal_precision', 'is_active'])]);

        return redirect()->route('admin.units.index')->with('status', 'Unit updated.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->parts()->exists() || $unit->binLocations()->exists()) {
            return back()->withErrors(['unit' => 'Cannot delete a unit that is still in use.']);
        }

        AuditLog::record('unit.deleted', $unit, $unit->only(['name', 'short_code']));
        $unit->delete();

        return redirect()->route('admin.units.index')->with('status', 'Unit deleted.');
    }
}
