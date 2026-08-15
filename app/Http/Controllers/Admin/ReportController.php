<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Services\Inventory\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reports) {}

    public function valuation(Request $request): View
    {
        $workshopId = $request->user()?->workshop_id ?? 0;

        return view('admin.reports.valuation', [
            'title' => 'Inventory valuation',
            'data' => $this->reports->inventoryValuation($workshopId),
            'currency' => setting('inventory.default_currency', 'USD'),
        ]);
    }

    public function lowStock(Request $request): View
    {
        $workshopId = $request->user()?->workshop_id ?? 0;
        $lowStock = $this->reports->lowStock($workshopId);

        return view('admin.reports.low-stock', [
            'title' => 'Low-stock report',
            'parts' => $lowStock,
        ]);
    }

    public function movements(Request $request): View
    {
        $workshopId = $request->user()?->workshop_id ?? 0;
        $type = $request->query('type');
        $movements = $this->reports->movementHistory($workshopId, $type, 200);
        $types = StockMovement::query()->distinct('type')->pluck('type');

        return view('admin.reports.movements', [
            'title' => 'Stock movement history',
            'movements' => $movements,
            'types' => $types,
            'type' => $type,
        ]);
    }

    public function topConsumed(Request $request): View
    {
        $workshopId = $request->user()?->workshop_id ?? 0;
        $days = (int) $request->query('days', 30);
        $from = now()->subDays($days);
        $to = now();

        return view('admin.reports.top-consumed', [
            'title' => 'Top consumed parts',
            'rows' => $this->reports->topConsumed($workshopId, $from, $to, 25),
            'days' => $days,
            'from' => $from,
            'to' => $to,
        ]);
    }

    // ---------------------------------------------------------------
    // CSV exports
    // ---------------------------------------------------------------

    public function valuationExport(Request $request): StreamedResponse
    {
        $this->authorizeIfNeeded($request, 'reports.export');

        $workshopId = $request->user()?->workshop_id ?? 0;
        $rows = $this->reports->inventoryValuation($workshopId);

        return $this->streamCsv('inventory-valuation.csv', ['Part', 'SKU', 'Bin', 'Quantity', 'Cost', 'Value'], function ($out) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'] ?? '',
                    $row['sku'] ?? '',
                    $row['bin_code'] ?? '',
                    number_format($row['quantity'] ?? 0, 2),
                    number_format($row['cost_price'] ?? 0, 2),
                    number_format($row['value'] ?? 0, 2),
                ]);
            }
        });
    }

    public function lowStockExport(Request $request): StreamedResponse
    {
        $this->authorizeIfNeeded($request, 'reports.export');

        $workshopId = $request->user()?->workshop_id ?? 0;
        $rows = $this->reports->lowStock($workshopId);

        return $this->streamCsv('low-stock.csv', ['Part', 'SKU', 'On hand', 'Reorder at'], function ($out) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['name'] ?? '',
                    $row['sku'] ?? '',
                    number_format($row['quantity'] ?? 0, 2),
                    number_format($row['reorder_threshold'] ?? 0, 2),
                ]);
            }
        });
    }

    public function movementsExport(Request $request): StreamedResponse
    {
        $this->authorizeIfNeeded($request, 'reports.export');

        $workshopId = $request->user()?->workshop_id ?? 0;
        $type = $request->query('type');
        $rows = $this->reports->movementHistory($workshopId, $type, 10000);

        return $this->streamCsv('movement-history.csv', ['Occurred', 'Type', 'Part', 'Bin', 'Quantity', 'Unit cost', 'Reason'], function ($out) use ($rows) {
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->occurred_at?->format('Y-m-d H:i:s'),
                    $row->type?->value ?? (string) $row->type,
                    $row->part?->name ?? '',
                    $row->inventoryItem?->bin?->code ?? '',
                    number_format($row->quantity ?? 0, 2),
                    number_format($row->unit_cost ?? 0, 2),
                    $row->reason ?? '',
                ]);
            }
        });
    }

    public function topConsumedExport(Request $request): StreamedResponse
    {
        $this->authorizeIfNeeded($request, 'reports.export');

        $workshopId = $request->user()?->workshop_id ?? 0;
        $days = (int) $request->query('days', 30);

        return $this->streamCsv('top-consumed.csv', ['Part', 'SKU', 'Quantity', 'Movements'], function ($out) use ($workshopId, $days) {
            foreach ($this->reports->topConsumed($workshopId, now()->subDays($days), now(), 1000) as $row) {
                fputcsv($out, [
                    $row['name'] ?? '',
                    $row['sku'] ?? '',
                    number_format($row['total_quantity'] ?? 0, 2),
                    $row['movement_count'] ?? 0,
                ]);
            }
        });
    }

    /**
     * Stream a CSV response — keeps memory flat on large exports.
     *
     * @param  list<string>  $headers
     */
    protected function streamCsv(string $filename, array $headers, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $writer) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            $writer($out);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function authorizeIfNeeded(Request $request, string $ability): void
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->hasPermission($ability)) {
            abort(403, "Missing permission: {$ability}");
        }
    }
}
