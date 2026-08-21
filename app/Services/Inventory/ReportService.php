<?php

namespace App\Services\Inventory;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\Unit;
use App\Models\Workshop;
use App\Scopes\WorkshopScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregations for the reports module.
 *
 * Every method takes a workshop_id so the global scope filter is
 * redundant — service code wraps its queries in
 * WorkshopScope::disabled() to opt out of the user-bound filter and
 * trusts the explicit WHERE clauses to enforce isolation.
 */
class ReportService
{
    /**
     * Sum of (quantity * cost_price) over every inventory bucket in the workshop.
     *
     * @return array{inventory_value: float, parts_in_stock: int, items_count: int}
     */
    public function inventoryValuation(int $workshopId): array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $rows = InventoryItem::query()
                ->where('workshop_id', $workshopId)
                ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) AS value, COUNT(*) AS items')
                ->first();

            $partsInStock = Part::query()
                ->where('workshop_id', $workshopId)
                ->whereHas('inventoryItems', fn ($q) => $q->where('workshop_id', $workshopId)->where('quantity', '>', 0))
                ->count();

            return [
                'inventory_value' => (float) ($rows->value ?? 0),
                'parts_in_stock' => (int) $partsInStock,
                'items_count' => (int) ($rows->items ?? 0),
            ];
        });
    }

    /**
     * Parts whose aggregated quantity across bins/batches has fallen to
     * or below their reorder_threshold.
     *
     * @return Collection<int, Part>
     */
    public function lowStock(int $workshopId): Collection
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            return Part::query()
                ->where('workshop_id', $workshopId)
                ->where('is_active', true)
                ->withSum('inventoryItems as on_hand', 'quantity')
                ->with('category:id,name')
                ->get()
                ->filter(function (Part $p) {
                    $onHand = (float) ($p->on_hand ?? 0);

                    return $onHand <= (float) $p->reorder_threshold;
                })
                ->sortBy('name')
                ->values();
        });
    }

    /**
     * Recent stock movements, newest first.
     *
     * @return Collection<int, StockMovement>
     */
    public function movementHistory(int $workshopId, ?string $type = null, int $limit = 100): Collection
    {
        return WorkshopScope::disabled(function () use ($workshopId, $type, $limit) {
            return StockMovement::query()
                ->where('workshop_id', $workshopId)
                ->when($type, fn (Builder $q) => $q->where('type', $type))
                ->with(['part:id,name,sku', 'bin:id,code', 'user:id,name'])
                ->latest('created_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Top N parts by outgoing movement quantity within a date range.
     *
     * @return Collection<int, object{part_id: int, name: string, sku: ?string, total_out: float}>
     */
    public function topConsumed(int $workshopId, \DateTimeInterface $from, \DateTimeInterface $to, int $limit = 10): Collection
    {
        return WorkshopScope::disabled(function () use ($workshopId, $from, $to, $limit) {
            return StockMovement::query()
                ->where('workshop_id', $workshopId)
                ->where('quantity', '<', 0) // outgoing movements are negative
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('part_id, SUM(ABS(quantity)) AS total_out')
                ->groupBy('part_id')
                ->orderByDesc('total_out')
                ->with('part:id,name,sku')
                ->limit($limit)
                ->get()
                ->map(function ($row) {
                    return (object) [
                        'part_id' => (int) $row->part_id,
                        'name' => $row->part?->name ?? "(deleted #{$row->part_id})",
                        'sku' => $row->part?->sku,
                        'total_out' => (float) $row->total_out,
                    ];
                });
        });
    }

    /**
     * Open purchase orders that are awaiting approval or delivery.
     *
     * @return Collection<int, PurchaseOrder>
     */
    public function openPurchaseOrders(int $workshopId): Collection
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            return PurchaseOrder::query()
                ->where('workshop_id', $workshopId)
                ->whereIn('status', ['draft', 'pending_approval', 'approved', 'ordered', 'partial'])
                ->with('supplier:id,name')
                ->latest('created_at')
                ->limit(50)
                ->get();
        });
    }

    /**
     * Workshop counts for the dashboard.
     *
     * @return array{workshops: int, parts: int, suppliers: int, equipment: int, units: int, tools: int, tools_available: int, tools_checked_out: int, tools_under_maintenance: int, tools_overdue_checkouts: int}
     */
    public function dashboardTotals(?int $workshopId = null): array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $toolsQuery = Tool::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId));
            $toolsByStatus = (clone $toolsQuery)
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all();

            $overdueCheckouts = ToolCheckout::query()
                ->whereNull('returned_at')
                ->whereNotNull('expected_return_at')
                ->where('expected_return_at', '<', now())
                ->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))
                ->count();

            return [
                'workshops' => Workshop::query()->when($workshopId, fn ($q) => $q->where('id', $workshopId))->count(),
                'parts' => Part::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))->count(),
                'suppliers' => Supplier::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))->count(),
                'equipment' => Equipment::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))->count(),
                'units' => Unit::query()->where('is_active', true)->count(),
                'categories' => PartCategory::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))->count(),
                'departments' => Department::query()->when($workshopId, fn ($q) => $q->where('workshop_id', $workshopId))->count(),
                'tools' => array_sum($toolsByStatus),
                'tools_available' => $toolsByStatus['available'] ?? 0,
                'tools_checked_out' => $toolsByStatus['checked_out'] ?? 0,
                'tools_under_maintenance' => $toolsByStatus['under_maintenance'] ?? 0,
                'tools_overdue_checkouts' => $overdueCheckouts,
            ];
        });
    }

    /**
     * Top N most-consumed parts over the last $days days, shaped for
     * Chart.js directly: parallel `labels` and `values` arrays. Reuses
     * `topConsumed()` so the SQL stays in one place.
     *
     * @return array{labels: string[], values: float[]}
     */
    public function topConsumedForChart(?int $workshopId, int $days = 30, int $limit = 10): array
    {
        // For global admins (workshopId=null) the service still needs a
        // workshop_id; aggregate across every workshop by passing a
        // sentinel and falling back to the no-filter branch.
        if ($workshopId === null) {
            return WorkshopScope::disabled(function () use ($days, $limit) {
                $rows = StockMovement::query()
                    ->where('quantity', '<', 0)
                    ->whereBetween('created_at', [now()->subDays($days), now()])
                    ->selectRaw('part_id, SUM(ABS(quantity)) AS total_out')
                    ->groupBy('part_id')
                    ->orderByDesc('total_out')
                    ->with('part:id,name,sku')
                    ->limit($limit)
                    ->get();

                return [
                    'labels' => $rows->map(fn ($r) => $r->part?->name ?? "(#{$r->part_id})")->all(),
                    'values' => $rows->map(fn ($r) => round((float) $r->total_out, 2))->all(),
                ];
            });
        }

        $rows = $this->topConsumed($workshopId, now()->subDays($days), now(), $limit);

        return [
            'labels' => $rows->pluck('name')->all(),
            'values' => $rows->pluck('total_out')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /**
     * Inventory value grouped by part category, shaped for Chart.js
     * doughnut. Slices sum to the same `inventory_value` returned by
     * `inventoryValuation()` (within rounding).
     *
     * @return array{labels: string[], values: float[], total: float}
     */
    public function inventoryValueByCategory(?int $workshopId): array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            // When scoping to a single workshop we group by the category's
            // primary key (id). When aggregating across every workshop for
            // a global admin, two workshops can each have their own
            // "Brakes" category — to avoid duplicate slices we group by
            // name only and SUM their values together.
            $groupByName = $workshopId === null;

            $rows = DB::table('inventory_items')
                ->join('parts', function ($join) use ($workshopId) {
                    $join->on('parts.id', '=', 'inventory_items.part_id')
                        ->when($workshopId, fn ($j) => $j->where('parts.workshop_id', $workshopId));
                })
                ->join('part_categories', function ($join) use ($workshopId) {
                    $join->on('part_categories.id', '=', 'parts.category_id')
                        ->when($workshopId, fn ($j) => $j->where('part_categories.workshop_id', $workshopId));
                })
                ->whereNotNull('parts.category_id')
                ->when($workshopId, fn ($q) => $q->where('inventory_items.workshop_id', $workshopId))
                ->groupBy('part_categories.name')
                ->selectRaw('part_categories.name AS label, SUM(inventory_items.quantity * inventory_items.cost_price) AS value')
                ->orderByDesc('value')
                ->get();

            $labels = $rows->pluck('label')->all();
            $values = $rows->pluck('value')->map(fn ($v) => round((float) $v, 2))->all();
            $total = array_sum($values);

            return [
                'labels' => $labels,
                'values' => $values,
                'total' => round($total, 2),
            ];
        });
    }
}
