<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Access\RolePermissionService;
use Illuminate\Database\Seeder;

/**
 * Seed the baseline RBAC dataset.
 *
 *  - ~70 permissions grouped by domain.
 *  - 5 built-in roles:
 *      * Super Admin       — every permission (effectively admin role)
 *      * Inventory Manager — full inventory + read-only catalog
 *      * Procurement Mgr   — full purchase orders + read-only suppliers
 *      * Warehouse Manager — bin locations, transfers, adjustments
 *      * Auditor           — read-only on everything + audit log access
 *
 * The user.role='admin' column still acts as the super-admin fast path
 * in `User::hasPermission()` and the `admin` route middleware. These
 * roles give non-admin staff finer-grained abilities.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var RolePermissionService $rbac */
        $rbac = app(RolePermissionService::class);

        $permissions = collect(self::PERMISSIONS())
            ->map(fn (array $p) => $rbac->ensurePermission(
                name: $p['name'],
                group: $p['group'],
                description: $p['description'] ?? null,
            ));

        // Index by name for role grants.
        $byName = $permissions->keyBy('name');

        foreach (self::ROLES() as $roleSpec) {
            $role = $rbac->ensureRole(
                name: $roleSpec['name'],
                slug: $roleSpec['slug'],
                description: $roleSpec['description'] ?? null,
                isSystem: $roleSpec['is_system'] ?? false,
            );

            // Resolve permission IDs from the role's grant list.
            $ids = collect($roleSpec['grants'])
                ->map(fn (string $name) => $byName->get($name)?->id)
                ->filter()
                ->values()
                ->all();

            $role->syncPermissions($ids);
        }
    }

    /**
     * @return list<array{name: string, group: string, description?: string}>
     */
    public static function PERMISSIONS(): array
    {
        return [
            // Catalog
            ['name' => 'products.view',       'group' => 'products',  'description' => 'View products'],
            ['name' => 'products.create',     'group' => 'products',  'description' => 'Create products'],
            ['name' => 'products.update',     'group' => 'products',  'description' => 'Edit products'],
            ['name' => 'products.delete',     'group' => 'products',  'description' => 'Delete (archive) products'],
            ['name' => 'products.import',     'group' => 'products',  'description' => 'Import products from CSV'],

            ['name' => 'categories.view',     'group' => 'catalog',   'description' => 'View categories'],
            ['name' => 'categories.manage',   'group' => 'catalog',   'description' => 'Create/update/delete categories'],
            ['name' => 'brands.view',         'group' => 'catalog',   'description' => 'View brands'],
            ['name' => 'brands.manage',       'group' => 'catalog',   'description' => 'Create/update/delete brands'],
            ['name' => 'units.view',          'group' => 'catalog',   'description' => 'View units'],
            ['name' => 'units.manage',        'group' => 'catalog',   'description' => 'Create/update/delete units'],

            // Warehousing
            ['name' => 'warehouses.view',     'group' => 'warehouses', 'description' => 'View warehouses (workshops)'],
            ['name' => 'warehouses.manage',   'group' => 'warehouses', 'description' => 'Create/update/delete warehouses'],
            ['name' => 'bin-locations.view',  'group' => 'warehouses', 'description' => 'View bin locations'],
            ['name' => 'bin-locations.manage', 'group' => 'warehouses', 'description' => 'Create/update/delete bin locations'],

            // Inventory operations
            ['name' => 'inventory.view',      'group' => 'inventory',  'description' => 'View inventory items & balances'],
            ['name' => 'inventory.adjust',    'group' => 'inventory',  'description' => 'Create stock adjustments'],
            ['name' => 'inventory.approve',   'group' => 'inventory',  'description' => 'Approve/reject stock adjustments'],
            ['name' => 'inventory.transfer',  'group' => 'inventory',  'description' => 'Create/dispatch/receive stock transfers'],

            // Procurement
            ['name' => 'purchase-orders.view',    'group' => 'procurement', 'description' => 'View purchase orders'],
            ['name' => 'purchase-orders.create',  'group' => 'procurement', 'description' => 'Create purchase orders'],
            ['name' => 'purchase-orders.submit',  'group' => 'procurement', 'description' => 'Submit purchase orders for approval'],
            ['name' => 'purchase-orders.approve', 'group' => 'procurement', 'description' => 'Approve purchase orders'],
            ['name' => 'purchase-orders.cancel',  'group' => 'procurement', 'description' => 'Cancel purchase orders'],
            ['name' => 'purchase-orders.receive', 'group' => 'procurement', 'description' => 'Receive goods against purchase orders'],

            ['name' => 'suppliers.view',     'group' => 'procurement', 'description' => 'View suppliers'],
            ['name' => 'suppliers.manage',   'group' => 'procurement', 'description' => 'Create/update/delete suppliers'],
            ['name' => 'goods-receipts.view', 'group' => 'procurement', 'description' => 'View goods receipts'],

            // Org
            ['name' => 'departments.view',   'group' => 'org', 'description' => 'View departments'],
            ['name' => 'departments.manage', 'group' => 'org', 'description' => 'Create/update/delete departments'],
            ['name' => 'equipment.view',     'group' => 'org', 'description' => 'View equipment'],
            ['name' => 'equipment.manage',   'group' => 'org', 'description' => 'Create/update/delete equipment'],

            // Access control
            ['name' => 'users.view',         'group' => 'access', 'description' => 'View users'],
            ['name' => 'users.manage',       'group' => 'access', 'description' => 'Create/update/delete users'],
            ['name' => 'roles.manage',       'group' => 'access', 'description' => 'Create/update/delete roles and grant permissions'],
            ['name' => 'permissions.view',   'group' => 'access', 'description' => 'View the permission catalogue'],

            // Reports
            ['name' => 'reports.view',       'group' => 'reports', 'description' => 'View all reports'],
            ['name' => 'reports.export',     'group' => 'reports', 'description' => 'Export reports to CSV'],

            // Audit
            ['name' => 'audit-logs.view',    'group' => 'audit', 'description' => 'View audit log entries'],
            ['name' => 'audit-logs.export',  'group' => 'audit', 'description' => 'Export audit logs to CSV'],

            // System
            ['name' => 'settings.view',      'group' => 'system', 'description' => 'View system settings'],
            ['name' => 'settings.manage',    'group' => 'system', 'description' => 'Edit system settings'],
        ];
    }

    /**
     * @return list<array{name: string, slug: string, description?: string, is_system?: bool, grants: list<string>}>
     */
    public static function ROLES(): array
    {
        return [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Built-in super-administrator; has every permission.',
                'is_system' => true,
                'grants' => [
                    'products.view', 'products.create', 'products.update', 'products.delete', 'products.import',
                    'categories.view', 'categories.manage',
                    'brands.view', 'brands.manage',
                    'units.view', 'units.manage',
                    'warehouses.view', 'warehouses.manage',
                    'bin-locations.view', 'bin-locations.manage',
                    'inventory.view', 'inventory.adjust', 'inventory.approve', 'inventory.transfer',
                    'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.submit', 'purchase-orders.approve', 'purchase-orders.cancel', 'purchase-orders.receive',
                    'suppliers.view', 'suppliers.manage', 'goods-receipts.view',
                    'departments.view', 'departments.manage',
                    'equipment.view', 'equipment.manage',
                    'users.view', 'users.manage', 'roles.manage', 'permissions.view',
                    'reports.view', 'reports.export',
                    'audit-logs.view', 'audit-logs.export',
                    'settings.view', 'settings.manage',
                ],
            ],
            [
                'name' => 'Inventory Manager',
                'slug' => 'inventory-manager',
                'description' => 'Full inventory operations and read-only catalog.',
                'is_system' => true,
                'grants' => [
                    'products.view',
                    'categories.view', 'brands.view', 'units.view',
                    'warehouses.view', 'bin-locations.view',
                    'inventory.view', 'inventory.adjust', 'inventory.approve', 'inventory.transfer',
                    'reports.view',
                    'goods-receipts.view',
                ],
            ],
            [
                'name' => 'Procurement Manager',
                'slug' => 'procurement-manager',
                'description' => 'Full purchase-order workflow and read-only suppliers.',
                'is_system' => true,
                'grants' => [
                    'suppliers.view', 'suppliers.manage',
                    'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.submit', 'purchase-orders.approve', 'purchase-orders.cancel', 'purchase-orders.receive',
                    'goods-receipts.view',
                    'products.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Warehouse Manager',
                'slug' => 'warehouse-manager',
                'description' => 'Manage warehouses, bins, transfers and adjustments.',
                'is_system' => true,
                'grants' => [
                    'warehouses.view', 'warehouses.manage',
                    'bin-locations.view', 'bin-locations.manage',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer',
                    'products.view',
                    'reports.view',
                ],
            ],
            [
                'name' => 'Auditor',
                'slug' => 'auditor',
                'description' => 'Read-only across the system plus audit log access.',
                'is_system' => true,
                'grants' => [
                    'products.view', 'categories.view', 'brands.view', 'units.view',
                    'warehouses.view', 'bin-locations.view',
                    'inventory.view', 'goods-receipts.view',
                    'purchase-orders.view', 'suppliers.view',
                    'departments.view', 'equipment.view',
                    'users.view', 'permissions.view',
                    'reports.view', 'reports.export',
                    'audit-logs.view', 'audit-logs.export',
                    'settings.view',
                ],
            ],
        ];
    }
}
