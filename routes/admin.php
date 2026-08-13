<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BinLocationController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EquipmentController;
use App\Http\Controllers\Admin\GoodsReceiptController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\SupplierCategoryController;
use App\Http\Controllers\Admin\SupplierController;
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
        Route::resource('categories',  CategoryController::class)->except(['show']);
        Route::resource('brands',      BrandController::class)->except(['show']);
        Route::resource('units',       UnitController::class)->except(['show']);
        Route::resource('departments', DepartmentController::class)->except(['show']);
        Route::resource('equipment',   EquipmentController::class);

        // Warehousing
        Route::resource('warehouses',    WarehouseController::class)->parameters(['warehouses' => 'warehouse']);
        Route::resource('bin-locations', BinLocationController::class)->except(['show'])->parameters(['bin-locations' => 'binLocation']);

        // Catalog: products + import/export
        Route::resource('products', ProductController::class)->parameters(['products' => 'product']);
        Route::post('products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('products-export', [ProductController::class, 'export'])->name('products.export');

        // Suppliers
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::resource('supplier-categories', SupplierCategoryController::class)
            ->parameters(['supplier-categories' => 'supplierCategory']);

        // Procurement
        Route::resource('purchase-orders', PurchaseOrderController::class)
            ->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::post('purchase-orders/{purchaseOrder}/submit',  [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])->name('purchase-orders.approve');
        Route::post('purchase-orders/{purchaseOrder}/cancel',  [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
        Route::match(['get', 'post'], 'purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');

        Route::resource('goods-receipts', GoodsReceiptController::class)
            ->only(['index', 'show'])
            ->parameters(['goods-receipts' => 'goodsReceipt']);

        // Inventory operations
        Route::resource('stock-adjustments', StockAdjustmentController::class)
            ->parameters(['stock-adjustments' => 'stockAdjustment']);
        Route::post('stock-adjustments/{stockAdjustment}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');
        Route::post('stock-adjustments/{stockAdjustment}/reject',  [StockAdjustmentController::class, 'reject'])->name('stock-adjustments.reject');

        Route::resource('stock-transfers', StockTransferController::class)
            ->parameters(['stock-transfers' => 'stockTransfer']);
        Route::post('stock-transfers/{stockTransfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('stock-transfers.dispatch');
        Route::post('stock-transfers/{stockTransfer}/receive',  [StockTransferController::class, 'receive'])->name('stock-transfers.receive');

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('inventory-valuation',         [ReportController::class, 'valuation'])->name('valuation');
            Route::get('inventory-valuation/export', [ReportController::class, 'valuationExport'])->name('valuation.export');
            Route::get('low-stock',                   [ReportController::class, 'lowStock'])->name('low-stock');
            Route::get('low-stock/export',           [ReportController::class, 'lowStockExport'])->name('low-stock.export');
            Route::get('movement-history',            [ReportController::class, 'movements'])->name('movements');
            Route::get('movement-history/export',    [ReportController::class, 'movementsExport'])->name('movements.export');
            Route::get('top-consumed',                [ReportController::class, 'topConsumed'])->name('top-consumed');
            Route::get('top-consumed/export',        [ReportController::class, 'topConsumedExport'])->name('top-consumed.export');
        });

        // Settings
        Route::get('system/settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('system/settings', [SettingController::class, 'update'])->name('settings.update');

        // Audit logs
        Route::get('audit-logs',        [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/{log}',  [AuditLogController::class, 'show'])->name('audit-logs.show');
        Route::get('audit-logs-export', [AuditLogController::class, 'export'])->name('audit-logs.export');

        // Access control
        Route::resource('users',       UserController::class);
        Route::resource('roles',       RoleController::class)->parameters(['roles' => 'role']);
        Route::resource('permissions', PermissionController::class)->only(['index', 'show'])->parameters(['permissions' => 'permission']);
    });
