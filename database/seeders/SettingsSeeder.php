<?php

namespace Database\Seeders;

use App\Services\SettingService;
use Illuminate\Database\Seeder;

/**
 * Seeds the default runtime configuration values. Both global and
 * the per-workshop-default group are stored under (workshop_id=null)
 * so they apply everywhere; administrators can override per workshop
 * later via /admin/system/settings.
 */
class SettingsSeeder extends Seeder
{
    /**
     * @var array<string, array{value: mixed, type: string, group: string, description: string}>
     */
    public const DEFAULTS = [
        // Inventory
        'inventory.allow_negative_stock' => [
            'value' => false, 'type' => 'bool', 'group' => 'inventory',
            'description' => 'Allow inventory_items.quantity to go below zero.',
        ],
        'inventory.require_adjustment_approval' => [
            'value' => true, 'type' => 'bool', 'group' => 'inventory',
            'description' => 'Require an approver (other than the requester) before a stock adjustment is applied.',
        ],
        'inventory.require_po_approval' => [
            'value' => true, 'type' => 'bool', 'group' => 'inventory',
            'description' => 'Require an approver before a purchase order is submitted to the supplier.',
        ],
        'inventory.default_currency' => [
            'value' => 'USD', 'type' => 'string', 'group' => 'inventory',
            'description' => 'ISO-4217 currency code for inventory valuation.',
        ],
        'inventory.low_stock_threshold_percent' => [
            'value' => 20, 'type' => 'int', 'group' => 'inventory',
            'description' => 'Percent of reorder_threshold below which a part is marked Critical.',
        ],
        'inventory.valuation_method' => [
            'value' => 'moving_average', 'type' => 'string', 'group' => 'inventory',
            'description' => 'Costing method: moving_average | fifo | lifo.',
        ],

        // Procurement numbering
        'po.number_format' => [
            'value' => 'PO-{YYYY}-{NNNN}', 'type' => 'string', 'group' => 'numbering',
            'description' => 'Format string for new purchase order numbers.',
        ],
        'grn.number_format' => [
            'value' => 'GRN-{YYYY}-{NNNN}', 'type' => 'string', 'group' => 'numbering',
            'description' => 'Format string for new goods receipt numbers.',
        ],

        // Adjustment numbering
        'stock_adjustment.number_format' => [
            'value' => 'ADJ-{YYYY}-{NNNN}', 'type' => 'string', 'group' => 'numbering',
            'description' => 'Format string for new stock adjustment numbers.',
        ],

        // Transfer numbering
        'stock_transfer.number_format' => [
            'value' => 'TRF-{YYYY}-{NNNN}', 'type' => 'string', 'group' => 'numbering',
            'description' => 'Format string for new stock transfer numbers.',
        ],
    ];

    public function run(): void
    {
        /** @var SettingService $service */
        $service = app(SettingService::class);

        foreach (self::DEFAULTS as $key => $row) {
            $service->set(
                key: $key,
                value: $row['value'],
                workshopId: null,
                group: $row['group'],
                type: $row['type'],
                description: $row['description'],
            );
        }

        // Force the cache invalidation so the next read sees the fresh values.
        $service->forgetCache(null);
    }
}
