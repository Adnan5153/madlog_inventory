<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Inventory\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin landing page. Counts come from live aggregate queries against
 * the database — never fabricated values.
 */
class DashboardController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function index(Request $request): View
    {
        $user = Auth::user();
        $workshopId = $user?->workshop_id;

        $totals = $this->reports->dashboardTotals($workshopId);

        // Total inventory value (Parts bucket table; multi-workshop rollup
        // for global admins via globalInventoryValue()).
        $valuation = $workshopId !== null
            ? $this->reports->inventoryValuation($workshopId)
            : $this->reports->globalInventoryValue();

        $lowStockCount = $workshopId
            ? $this->reports->lowStock($workshopId)->count()
            : 0;

        // Compute the monthly aggregation ONCE; both the bar chart and
        // the line chart render the same dataset.
        $monthly = $this->reports->monthlyStockMovements($workshopId);

        $charts = [
            // Existing — preserved unchanged.
            'topConsumed' => $this->reports->topConsumedForChart($workshopId),
            'inventoryByCat' => $this->reports->inventoryValueByCategory($workshopId),
            // New keys for the inventory-intelligence section.
            'monthlyMovements' => $monthly,
            'movementTrend' => $monthly,
            'quantityByCategory' => $this->reports->inventoryQuantityByCategory($workshopId),
            'stockValueByCat' => $this->reports->stockValueByCategory($workshopId),
            'batteries' => $this->reports->batteryQuantityByType($workshopId),
            'lubricants' => $this->reports->lubricantQuantityByType($workshopId),
            'tools' => $this->reports->toolQuantityByCategory($workshopId),
        ];

        $recentMovements = $this->reports->recentStockMovements($workshopId);

        return view('admin.dashboard', [
            'title' => 'Dashboard',
            'totals' => $totals,
            'valuation' => $valuation,
            'user' => $user,
            'lowStockCount' => $lowStockCount,
            'recentMovements' => $recentMovements,
            'charts' => $charts,
        ]);
    }
}
