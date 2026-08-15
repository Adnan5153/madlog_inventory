<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Scopes\WorkshopScope;
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

        $lowStockCount = $workshopId
            ? $this->reports->lowStock($workshopId)->count()
            : 0;

        $recentActivity = WorkshopScope::disabled(function () use ($workshopId) {
            $q = AuditLog::query()->latest('created_at')->limit(15);
            if ($workshopId !== null) {
                $q->where('workshop_id', $workshopId);
            }

            return $q->get();
        });

        $charts = [
            'topConsumed' => $this->reports->topConsumedForChart($workshopId),
            'inventoryByCat' => $this->reports->inventoryValueByCategory($workshopId),
        ];

        return view('admin.dashboard', [
            'title' => 'Dashboard',
            'totals' => $totals,
            'user' => $user,
            'lowStockCount' => $lowStockCount,
            'recentActivity' => $recentActivity,
            'charts' => $charts,
        ]);
    }
}
