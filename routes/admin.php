<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BatteryController;
use App\Http\Controllers\Admin\BatteryStockAdjustmentController;
use App\Http\Controllers\Admin\BinLocationController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EquipmentConsumableController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Admin\LubricantController;
use App\Http\Controllers\Admin\LubricantStockAdjustmentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierCategoryController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ToolCategoryController;
use App\Http\Controllers\Admin\ToolController;
use App\Http\Controllers\Admin\ToolDashboardController;
use App\Http\Controllers\Admin\ToolMaintenanceController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
 * Admin routes. Restricted to users with role=admin. Global admins
 * (workshop_id = null) see every workshop; workshop-scoped admins see
 * only their own. Per-workshop scoping is enforced by WorkshopScope
 * and policies, not by these routes.
 */

Route::middleware(['auth', 'verified', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Master-data CRUD
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::get('categories-search', [CategoryController::class, 'search'])->name('categories.search');
        Route::resource('units', UnitController::class)->except(['show']);
        Route::get('units-search', [UnitController::class, 'search'])->name('units.search');
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::get('departments-search', [DepartmentController::class, 'search'])->name('departments.search');
        Route::resource('equipment', EquipmentController::class);
        Route::get('equipment-search', [EquipmentController::class, 'search'])->name('equipment.search');

        // Equipment consumables (assign / install / consume / replace / remove)
        // IMPORTANT: literal sub-routes (dashboard, consumption, export, search)
        // MUST be declared BEFORE Route::resource, otherwise Laravel's
        // `equipment-consumables/{equipmentConsumable}` show route will eat
        // them and try to bind the literal as a model id.
        Route::get('equipment-consumables/dashboard', [EquipmentConsumableController::class, 'dashboard'])->name('equipment-consumables.dashboard');
        Route::get('equipment-consumables/consumption', [EquipmentConsumableController::class, 'consumptionReport'])->name('equipment-consumables.report.consumption');
        Route::get('equipment-consumables-export', [EquipmentConsumableController::class, 'export'])->name('equipment-consumables.export');
        Route::get('equipment-consumables-search', [EquipmentConsumableController::class, 'search'])->name('equipment-consumables.search');
        Route::resource('equipment-consumables', EquipmentConsumableController::class)
            ->parameters(['equipment-consumables' => 'equipmentConsumable'])
            ->except(['destroy']);
        Route::delete('equipment-consumables/{equipmentConsumable}', [EquipmentConsumableController::class, 'destroy'])->name('equipment-consumables.destroy');
        Route::post('equipment-consumables/{equipmentConsumable}/consume', [EquipmentConsumableController::class, 'consume'])->name('equipment-consumables.consume');
        Route::post('equipment-consumables/{equipmentConsumable}/replace', [EquipmentConsumableController::class, 'replace'])->name('equipment-consumables.replace');
        Route::post('equipment-consumables/{equipmentConsumable}/remove', [EquipmentConsumableController::class, 'remove'])->name('equipment-consumables.remove');

        // Equipment-anchored consumables list
        Route::get('equipment/{equipment}/consumables', [EquipmentConsumableController::class, 'forEquipment'])->name('equipment.equipment-consumables.index');

        // Warehousing
        Route::resource('warehouses', WarehouseController::class)->parameters(['warehouses' => 'warehouse']);
        Route::get('warehouses-search', [WarehouseController::class, 'search'])->name('warehouses.search');
        Route::resource('bin-locations', BinLocationController::class)->except(['show'])->parameters(['bin-locations' => 'binLocation']);
        Route::get('bin-locations-search', [BinLocationController::class, 'search'])->name('bin-locations.search');

        // Catalog: products + import/export
        Route::resource('products', ProductController::class)->parameters(['products' => 'product']);
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products-export', [ProductController::class, 'export'])->name('products.export');
        Route::get('products-search', [ProductController::class, 'search'])->name('products.search');

        // Catalog: batteries
        Route::resource('batteries', BatteryController::class)->parameters(['batteries' => 'battery']);
        Route::get('batteries-search', [BatteryController::class, 'search'])->name('batteries.search');

        // Catalog: lubricants
        Route::resource('lubricants', LubricantController::class)->parameters(['lubricants' => 'lubricant']);
        Route::get('lubricants-search', [LubricantController::class, 'search'])->name('lubricants.search');

        // Tooling
        Route::get('tools/dashboard', [ToolDashboardController::class, 'index'])->name('tools.dashboard');
        Route::resource('tools', ToolController::class)->parameters(['tools' => 'tool']);
        Route::get('tools-search', [ToolController::class, 'search'])->name('tools.search');
        Route::post('tools/{tool}/checkout', [ToolController::class, 'checkout'])->name('tools.checkout');
        Route::post('tools/{tool}/checkin', [ToolController::class, 'checkin'])->name('tools.checkin');
        Route::resource('tools/{tool}/maintenance', ToolMaintenanceController::class)
            ->parameters(['maintenance' => 'maintenanceRecord'])
            ->names('tool-maintenance');
        Route::get('tools/{tool}/maintenance-search', [ToolMaintenanceController::class, 'search'])->name('tool-maintenance.search');
        Route::resource('tool-categories', ToolCategoryController::class)
            ->parameters(['tool-categories' => 'toolCategory']);
        Route::get('tool-categories-search', [ToolCategoryController::class, 'search'])->name('tool-categories.search');

        // Suppliers
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::get('suppliers-search', [SupplierController::class, 'search'])->name('suppliers.search');
        Route::resource('supplier-categories', SupplierCategoryController::class)
            ->parameters(['supplier-categories' => 'supplierCategory']);
        Route::get('supplier-categories-search', [SupplierCategoryController::class, 'search'])->name('supplier-categories.search');

        // Procurement
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::post('purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::match(['get', 'post'], 'purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::get('purchase-orders-search', [PurchaseOrderController::class, 'search'])->name('purchase-orders.search');

        Route::resource('goods-receipts', GoodsReceiptController::class)
            ->only(['index', 'show'])
            ->parameters(['goods-receipts' => 'goodsReceipt']);
        Route::get('goods-receipts-search', [GoodsReceiptController::class, 'search'])->name('goods-receipts.search');

        // Inventory operations
        Route::resource('stock-adjustments', StockAdjustmentController::class)
            ->parameters(['stock-adjustments' => 'stockAdjustment']);
        Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
        Route::post('stock-adjustments/{stockAdjustment}/reject', [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');
        Route::get('stock-adjustments-search', [StockAdjustmentController::class, 'search'])->name('stock-adjustments.search');

        Route::resource('stock-transfers', StockTransferController::class)
            ->parameters(['stock-transfers' => 'stockTransfer']);
        Route::post('stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');
        Route::get('stock-transfers-search', [StockTransferController::class, 'search'])->name('stock-transfers.search');

        // Battery stock adjustments
        Route::resource('battery-stock-adjustments', BatteryStockAdjustmentController::class)
            ->parameters(['battery-stock-adjustments' => 'batteryStockAdjustment'])
            ->except(['destroy', 'edit', 'update']);
        Route::post('battery-stock-adjustments/{batteryStockAdjustment}/approve', [BatteryStockAdjustmentController::class, 'approve'])->name('battery-stock-adjustments.approve');
        Route::post('battery-stock-adjustments/{batteryStockAdjustment}/reject', [BatteryStockAdjustmentController::class, 'reject'])->name('battery-stock-adjustments.reject');
        Route::get('battery-stock-adjustments-search', [BatteryStockAdjustmentController::class, 'search'])->name('battery-stock-adjustments.search');

        // Lubricant stock adjustments
        Route::resource('lubricant-stock-adjustments', LubricantStockAdjustmentController::class)
            ->parameters(['lubricant-stock-adjustments' => 'lubricantStockAdjustment'])
            ->except(['destroy', 'edit', 'update']);
        Route::post('lubricant-stock-adjustments/{lubricantStockAdjustment}/approve', [LubricantStockAdjustmentController::class, 'approve'])->name('lubricant-stock-adjustments.approve');
        Route::post('lubricant-stock-adjustments/{lubricantStockAdjustment}/reject', [LubricantStockAdjustmentController::class, 'reject'])->name('lubricant-stock-adjustments.reject');
        Route::get('lubricant-stock-adjustments-search', [LubricantStockAdjustmentController::class, 'search'])->name('lubricant-stock-adjustments.search');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('inventory-valuation', [ReportController::class, 'valuation'])->name('valuation');
            Route::get('inventory-valuation/export', [ReportController::class, 'valuationExport'])->name('valuation.export');
            Route::get('low-stock', [ReportController::class, 'lowStock'])->name('low-stock');
            Route::get('low-stock/export', [ReportController::class, 'lowStockExport'])->name('low-stock.export');
            Route::get('movement-history', [ReportController::class, 'movements'])->name('movements');
            Route::get('movement-history/export', [ReportController::class, 'movementsExport'])->name('movements.export');
            Route::get('top-consumed', [ReportController::class, 'topConsumed'])->name('top-consumed');
            Route::get('top-consumed/export', [ReportController::class, 'topConsumedExport'])->name('top-consumed.export');
        });

        // Settings
        Route::get('system/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('system/settings', [SettingController::class, 'update'])->name('settings.update');

        // Audit logs
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs-search', [AuditLogController::class, 'search'])->name('audit-logs.search');
        Route::get('audit-logs/{log}', [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::get('audit-logs-export', [AuditLogController::class, 'export'])->name('audit-logs.export');

        // Access control
        Route::resource('users', UserController::class);
        Route::get('users-search', [UserController::class, 'search'])->name('users.search');
        Route::resource('roles', RoleController::class)->parameters(['roles' => 'role']);
        Route::resource('permissions', PermissionController::class)->only(['index', 'show'])->parameters(['permissions' => 'permission']);
    });
