<?php

namespace App\Services\Inventory;

use App\Models\Battery;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\InventoryItem;
use App\Models\Lubricant;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use App\Scopes\WorkshopScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Cross-workshop inventory value (used by global admins on the dashboard).
     *
     * @return array{inventory_value: float, parts_in_stock: int, items_count: int}
     */
    public function globalInventoryValue(): array
    {
        return WorkshopScope::disabled(function () {
            $rows = InventoryItem::query()
                ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) AS value, COUNT(*) AS items')
                ->first();

            return [
                'inventory_value' => (float) ($rows->value ?? 0),
                'items_count' => (int) ($rows->items ?? 0),
                'parts_in_stock' => (int) Part::query()
                    ->whereHas('inventoryItems', fn ($q) => $q->where('quantity', '>', 0))
                    ->count(),
            ];
        });
    }

    /**
     * Monthly stock-in / stock-out totals over the last $months months,
     * zero-filled so every month in the window is present.
     *
     * @return array{labels: string[], stockIn: float[], stockOut: float[]}
     */
    public function monthlyStockMovements(?int $workshopId, int $months = 12): array
    {
        $months = max(1, min(36, $months));
        $now = CarbonImmutable::now()->startOfMonth();
        $start = $now->subMonths($months - 1);

        return WorkshopScope::disabled(function () use ($workshopId, $months, $start) {
            $driver = DB::connection()->getDriverName();
            $expr = $driver === 'sqlite'
                ? "strftime('%Y-%m', occurred_at)"
                : "DATE_FORMAT(occurred_at, '%Y-%m')";

            $bindings = [$start->toDateTimeString()];
            $scopeClause = '';
            if ($workshopId !== null) {
                $scopeClause = ' AND workshop_id = ?';
                $bindings[] = $workshopId;
            }

            $rows = DB::select(
                "SELECT {$expr} AS ym,
                        COALESCE(SUM(CASE WHEN quantity > 0 THEN  quantity ELSE 0 END), 0) AS qty_in,
                        COALESCE(SUM(CASE WHEN quantity < 0 THEN -quantity ELSE 0 END), 0) AS qty_out
                   FROM stock_movements
                  WHERE occurred_at >= ?{$scopeClause}
                  GROUP BY ym
                  ORDER BY ym ASC",
                $bindings,
            );

            // Zero-fill to a contiguous month window.
            $indexed = [];
            foreach ($rows as $row) {
                $indexed[$row->ym] = [
                    'in' => (float) $row->qty_in,
                    'out' => (float) $row->qty_out,
                ];
            }

            $labels = [];
            $stockIn = [];
            $stockOut = [];
            for ($i = 0; $i < $months; $i++) {
                $ym = $start->addMonths($i)->format('Y-m');
                $labels[] = $ym;
                $stockIn[] = round($indexed[$ym]['in'] ?? 0.0, 2);
                $stockOut[] = round($indexed[$ym]['out'] ?? 0.0, 2);
            }

            return [
                'labels' => $labels,
                'stockIn' => $stockIn,
                'stockOut' => $stockOut,
            ];
        });
    }

    /**
     * Inventory QUANTITY (not value) grouped by PartCategory.
     *
     * @return array{labels: string[], values: float[]}
     */
    public function inventoryQuantityByCategory(?int $workshopId): array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $bindings = [];
            $scopeClause = '';
            if ($workshopId !== null) {
                $scopeClause = ' AND ii.workshop_id = ?';
                $bindings[] = $workshopId;
            }

            $rows = DB::select(
                "SELECT pc.name AS label, SUM(ii.quantity) AS qty
                   FROM part_categories pc
                   JOIN parts p ON p.category_id = pc.id
                   JOIN inventory_items ii ON ii.part_id = p.id
                  WHERE ii.quantity > 0{$scopeClause}
                  GROUP BY pc.id, pc.name
                  ORDER BY qty DESC",
                $bindings,
            );

            $rows = array_values(array_filter($rows, fn ($r) => (float) $r->qty > 0));

            return [
                'labels' => array_map(fn ($r) => (string) $r->label, $rows),
                'values' => array_map(fn ($r) => round((float) $r->qty, 2), $rows),
            ];
        });
    }

    /**
     * Stock value (quantity × cost_price) across all three subsystems,
     * grouped by category / type label, top N ranked.
     *
     * @return array{labels: string[], values: float[], total: float}
     */
    public function stockValueByCategory(?int $workshopId, int $limit = 10): array
    {
        return WorkshopScope::disabled(function () use ($workshopId, $limit) {
            $wsParts = $workshopId !== null ? ' AND ii.workshop_id = ?' : '';
            $wsBat = $workshopId !== null ? ' AND bi.workshop_id = ?' : '';
            $wsLub = $workshopId !== null ? ' AND li.workshop_id = ?' : '';

            $bindings = [];
            if ($workshopId !== null) {
                $bindings[] = $workshopId;
                $bindings[] = $workshopId;
                $bindings[] = $workshopId;
            }
            $bindings[] = $limit;

            $rows = DB::select(
                "SELECT label, SUM(value) AS value FROM (
                    SELECT pc.name AS label, ii.quantity * ii.cost_price AS value
                      FROM inventory_items ii
                      JOIN parts p ON p.id = ii.part_id
                      JOIN part_categories pc ON pc.id = p.category_id
                     WHERE ii.quantity > 0{$wsParts}
                    UNION ALL
                    SELECT b.battery_type AS label, bi.quantity * bi.cost_price AS value
                      FROM battery_inventory_items bi
                      JOIN batteries b ON b.id = bi.battery_id
                     WHERE bi.quantity > 0 AND bi.deleted_at IS NULL{$wsBat}
                    UNION ALL
                    SELECT l.lubricant_type AS label, li.quantity * li.cost_price AS value
                      FROM lubricant_inventory_items li
                      JOIN lubricants l ON l.id = li.lubricant_id
                     WHERE li.quantity > 0 AND li.deleted_at IS NULL{$wsLub}
                 ) t
                 GROUP BY label
                 ORDER BY value DESC
                 LIMIT ?",
                $bindings,
            );

            $labels = array_map(
                fn ($r) => Str::headline((string) $r->label),
                $rows,
            );
            $values = array_map(fn ($r) => round((float) $r->value, 2), $rows);
            $total = array_sum($values);

            return [
                'labels' => $labels,
                'values' => $values,
                'total' => round($total, 2),
            ];
        });
    }

    /**
     * Battery quantity grouped by `battery_type`. Returns null when the
     * subsystem has no data so the dashboard can skip the card entirely.
     *
     * @return array{labels: string[], values: float[]}|null
     */
    public function batteryQuantityByType(?int $workshopId): ?array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $bindings = [];
            $scopeClause = '';
            if ($workshopId !== null) {
                $scopeClause = ' AND bi.workshop_id = ?';
                $bindings[] = $workshopId;
            }

            $rows = DB::select(
                "SELECT b.battery_type AS label, SUM(bi.quantity) AS qty
                   FROM battery_inventory_items bi
                   JOIN batteries b ON b.id = bi.battery_id
                  WHERE bi.quantity > 0 AND bi.deleted_at IS NULL{$scopeClause}
                  GROUP BY b.battery_type
                  ORDER BY qty DESC",
                $bindings,
            );

            if (empty($rows)) {
                return null;
            }

            return [
                'labels' => array_map(
                    fn ($r) => Str::headline((string) $r->label),
                    $rows,
                ),
                'values' => array_map(fn ($r) => round((float) $r->qty, 2), $rows),
            ];
        });
    }

    /**
     * Lubricant quantity grouped by `lubricant_type`. Returns null when empty.
     *
     * @return array{labels: string[], values: float[]}|null
     */
    public function lubricantQuantityByType(?int $workshopId): ?array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $bindings = [];
            $scopeClause = '';
            if ($workshopId !== null) {
                $scopeClause = ' AND li.workshop_id = ?';
                $bindings[] = $workshopId;
            }

            $rows = DB::select(
                "SELECT l.lubricant_type AS label, SUM(li.quantity) AS qty
                   FROM lubricant_inventory_items li
                   JOIN lubricants l ON l.id = li.lubricant_id
                  WHERE li.quantity > 0 AND li.deleted_at IS NULL{$scopeClause}
                  GROUP BY l.lubricant_type
                  ORDER BY qty DESC",
                $bindings,
            );

            if (empty($rows)) {
                return null;
            }

            return [
                'labels' => array_map(
                    fn ($r) => Str::headline((string) $r->label),
                    $rows,
                ),
                'values' => array_map(fn ($r) => round((float) $r->qty, 2), $rows),
            ];
        });
    }

    /**
     * Tool count grouped by category (tools are serialised assets, not bucketed stock).
     *
     * @return array{labels: string[], values: float[]}|null
     */
    public function toolQuantityByCategory(?int $workshopId): ?array
    {
        return WorkshopScope::disabled(function () use ($workshopId) {
            $bindings = [];
            $scopeClause = '';
            if ($workshopId !== null) {
                $scopeClause = ' AND t.workshop_id = ?';
                $bindings[] = $workshopId;
            }

            $rows = DB::select(
                "SELECT COALESCE(tc.name, 'Uncategorized') AS label, COUNT(t.id) AS qty
                   FROM tools t
                   LEFT JOIN tool_categories tc ON tc.id = t.category_id AND tc.deleted_at IS NULL
                  WHERE 1=1{$scopeClause}
                  GROUP BY tc.id, tc.name
                  ORDER BY qty DESC",
                $bindings,
            );

            $rows = array_values(array_filter($rows, fn ($r) => (int) $r->qty > 0));
            if (empty($rows)) {
                return null;
            }

            return [
                'labels' => array_map(fn ($r) => (string) $r->label, $rows),
                'values' => array_map(fn ($r) => (float) $r->qty, $rows),
            ];
        });
    }

    /**
     * Recent stock movements across Parts, Batteries, and Lubricants.
     * Returns a flat array of DTO-style arrays ordered newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentStockMovements(?int $workshopId, int $limit = 10): array
    {
        return WorkshopScope::disabled(function () use ($workshopId, $limit) {
            $wsParts = $workshopId !== null ? ' AND sm.workshop_id = ?' : '';
            $wsBat = $workshopId !== null ? ' AND bsm.workshop_id = ?' : '';
            $wsLub = $workshopId !== null ? ' AND lsm.workshop_id = ?' : '';

            $bindings = [];
            if ($workshopId !== null) {
                $bindings[] = $workshopId;
                $bindings[] = $workshopId;
                $bindings[] = $workshopId;
            }
            $bindings[] = $limit;

            $rows = DB::select(
                "SELECT source, id, workshop_id, at, type, quantity, unit_cost,
                        reference_type, reference_id, user_id, product_name, sku_code
                   FROM (
                       SELECT 'part' AS source, sm.id, sm.workshop_id, sm.created_at AS at,
                              sm.type, sm.quantity, sm.unit_cost,
                              sm.reference_type, sm.reference_id, sm.user_id,
                              p.name AS product_name, p.sku AS sku_code
                         FROM stock_movements sm
                         JOIN parts p ON p.id = sm.part_id
                        WHERE 1=1{$wsParts}
                       UNION ALL
                       SELECT 'battery' AS source, bsm.id, bsm.workshop_id, bsm.created_at AS at,
                              bsm.type, bsm.quantity, bsm.unit_cost,
                              bsm.reference_type, bsm.reference_id, bsm.user_id,
                              b.name AS product_name, b.sku AS sku_code
                         FROM battery_stock_movements bsm
                         JOIN batteries b ON b.id = bsm.battery_id
                        WHERE 1=1{$wsBat}
                       UNION ALL
                       SELECT 'lubricant' AS source, lsm.id, lsm.workshop_id, lsm.created_at AS at,
                              lsm.type, lsm.quantity, lsm.unit_cost,
                              lsm.reference_type, lsm.reference_id, lsm.user_id,
                              l.name AS product_name, l.sku AS sku_code
                         FROM lubricant_stock_movements lsm
                         JOIN lubricants l ON l.id = lsm.lubricant_id
                        WHERE 1=1{$wsLub}
                   ) t
                  ORDER BY at DESC
                  LIMIT ?",
                $bindings,
            );

            if (empty($rows)) {
                return [];
            }

            // Batch-resolve user names to avoid N+1.
            $userIds = array_values(array_unique(array_filter(
                array_map(fn ($r) => $r->user_id, $rows),
                fn ($id) => $id !== null,
            )));
            $userNames = empty($userIds)
                ? []
                : User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();

            return array_map(function ($r) use ($userNames) {
                $qty = (float) $r->quantity;

                return [
                    'source' => (string) $r->source,
                    'id' => (int) $r->id,
                    'date' => CarbonImmutable::parse($r->at),
                    'type' => (string) $r->type,
                    'product_name' => (string) $r->product_name,
                    'sku' => $r->sku_code !== null ? (string) $r->sku_code : null,
                    'quantity' => $qty,
                    'direction' => $qty >= 0 ? 'in' : 'out',
                    'reference_type' => $r->reference_type,
                    'reference_id' => $r->reference_id !== null ? (int) $r->reference_id : null,
                    'user_name' => $r->user_id !== null
                        ? ($userNames[$r->user_id] ?? null)
                        : null,
                ];
            }, $rows);
        });
    }
}
