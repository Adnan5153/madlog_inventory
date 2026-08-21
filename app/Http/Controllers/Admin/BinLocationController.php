<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HasLiveSearch;
use App\Http\Controllers\Admin\Concerns\HasWorkshopPicker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBinLocationRequest;
use App\Http\Requests\Admin\UpdateBinLocationRequest;
use App\Models\AuditLog;
use App\Models\BinLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BinLocationController extends Controller
{
    use HasLiveSearch;
    use HasWorkshopPicker;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $zone = $request->query('zone');

        $hasFilters = collect($request->query())
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->isNotEmpty();

        $binLocations = $this->buildBinLocationsQuery($q, $active, $zone)
            ->paginate(20)
            ->withQueryString();

        $zones = BinLocation::query()->whereNotNull('zone')->distinct()->orderBy('zone')->pluck('zone');

        return view('admin.bin-locations.index', [
            'title' => 'Bin locations',
            'binLocations' => $binLocations,
            'hasFilters' => $hasFilters,
            'q' => $q,
            'active' => $active,
            'zone' => $zone,
            'zones' => $zones,
        ]);
    }

    /**
     * Live-search JSON endpoint for the bin locations index.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $active = $request->query('active');
        $zone = $request->query('zone');

        return $this->renderLiveSearch(
            request: $request,
            view: 'admin.bin-locations._row-template',
            singular: 'binLocation',
            builder: fn () => $this->buildBinLocationsQuery($q, $active, $zone),
        );
    }

    /**
     * Shared filtered query used by both index() and search(). Mirrors the
     * original index() filter exactly.
     */
    private function buildBinLocationsQuery(string $q, ?string $active, ?string $zone)
    {
        return BinLocation::query()
            ->when($q !== '', fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('zone', 'like', "%{$q}%")
                    ->orWhere('aisle', 'like', "%{$q}%")
                    ->orWhere('shelf', 'like', "%{$q}%");
            }))
            ->when($active === 'yes', fn ($qb) => $qb->where('is_active', true))
            ->when($active === 'no', fn ($qb) => $qb->where('is_active', false))
            ->when($zone, fn ($qb) => $qb->where('zone', $zone))
            ->withSum('inventoryItems as on_hand', 'quantity')
            ->orderBy('code');
    }

    /**
     * The row template (`_row-template.blade.php`) loops over `$binLocations`.
     */
    protected function singularNoun(): string
    {
        return 'binLocation';
    }

    public function create(): View
    {
        return view('admin.bin-locations.create', [
            'title' => 'New bin location',
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function store(StoreBinLocationRequest $request): RedirectResponse
    {
        $bin = BinLocation::create($request->validated());
        AuditLog::record('bin_location.created', $bin, $bin->only(['code', 'zone', 'aisle', 'workshop_id']));

        return redirect()->route('admin.bin-locations.index')->with('status', 'Bin location created.');
    }

    public function edit(BinLocation $binLocation): View
    {
        return view('admin.bin-locations.edit', [
            'title' => 'Edit bin location',
            'bin' => $binLocation,
            'workshops' => $this->workshopsForForm(),
        ]);
    }

    public function update(UpdateBinLocationRequest $request, BinLocation $binLocation): RedirectResponse
    {
        $before = $binLocation->only(['code', 'zone', 'aisle', 'shelf', 'is_active']);
        $binLocation->update($request->validated());
        AuditLog::record('bin_location.updated', $binLocation, ['before' => $before, 'after' => $binLocation->only(['code', 'zone', 'aisle', 'shelf', 'is_active'])]);

        return redirect()->route('admin.bin-locations.index')->with('status', 'Bin location updated.');
    }

    public function destroy(BinLocation $binLocation): RedirectResponse
    {
        if ($binLocation->inventoryItems()->exists()) {
            return back()->withErrors(['bin' => 'Cannot delete a bin that still holds inventory. Archive it instead.']);
        }

        AuditLog::record('bin_location.deleted', $binLocation, $binLocation->only(['code']));
        $binLocation->delete();

        return redirect()->route('admin.bin-locations.index')->with('status', 'Bin location deleted.');
    }
}
