<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\Brand;
use App\Models\InventoryItem;
use App\Models\JobCard;
use App\Models\JobCardPart;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * DevelopmentSeeder builds a realistic-looking multi-tenant dataset:
 *
 *   - 2 workshops
 *   - 1 global admin (sees everything)
 *   - per workshop: 1 workshop admin + 3 staff (1 storekeeper + 2 mechanics)
 *   - per workshop: 8 categories, 12 brands, 30+ parts, 20 bins,
 *                   inventory items (some low stock), recent movements,
 *                   a few purchase orders (mixed status), open job cards,
 *                   tools + checkouts, and a handful of audit log rows.
 *
 * Run with `php artisan db:seed --class=DevelopmentSeeder`.
 *
 * NOTE: DatabaseSeeder has been left as the default `php artisan db:seed`
 * entry point and delegates here in non-production environments.
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Workshops ----
        $workshops = Workshop::factory()
            ->count(2)
            ->sequence(
                ['name' => 'Madlog Demo Workshop A', 'slug' => 'demo-a'],
                ['name' => 'Madlog Demo Workshop B', 'slug' => 'demo-b'],
            )
            ->create();

        // ---- Global admin ----
        User::factory()->globalAdmin()->create([
            'name' => 'Global Admin',
            'email' => 'global@madlogstore.test',
            'password' => Hash::make('password'),
        ]);

        // ---- Per-workshop users, catalog, and operations data ----
        foreach ($workshops as $workshop) {
            $this->seedWorkshop($workshop);
        }

        $this->command?->info('DevelopmentSeeder: done.');
    }

    private function seedWorkshop(Workshop $workshop): void
    {
        // Workshop-scoped admin
        User::factory()->workshopAdmin()->create([
            'workshop_id' => $workshop->id,
            'name' => "Admin — {$workshop->name}",
            'email' => "admin-{$workshop->slug}@madlogstore.test",
            'password' => Hash::make('password'),
        ]);

        // 1 storekeeper + 2 mechanics
        User::factory()->staff()->create([
            'workshop_id' => $workshop->id,
            'name' => "Storekeeper — {$workshop->name}",
            'email' => "store-{$workshop->slug}@madlogstore.test",
            'password' => Hash::make('password'),
        ]);

        $mechanics = User::factory()->staff()->count(2)->create([
            'workshop_id' => $workshop->id,
        ]);
        $mechanics->each(function (User $mechanic, int $i) use ($workshop) {
            $mechanic->update([
                'name' => "Mechanic ".($i + 1)." — {$workshop->name}",
                'email' => "mech-{$workshop->slug}-".($i + 1).'@madlogstore.test',
                'password' => Hash::make('password'),
            ]);
        });

        // ---- Catalog ----
        $categories = PartCategory::factory()
            ->count(8)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();

        $brands = Brand::factory()
            ->count(12)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();

        $parts = Part::factory()
            ->count(35)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create([
                'category_id' => $categories->random()->id,
                'brand_id' => $brands->random()->id,
            ]);

        // Mark a few parts as low stock by giving their inventory items small quantities
        $lowStockParts = $parts->random(6);

        $bins = BinLocation::factory()
            ->count(20)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();

        // ---- Inventory items: most parts get one bucket, some get two ----
        $items = collect();
        foreach ($parts as $part) {
            $bucketCount = $part->id % 4 === 0 ? 2 : 1;
            for ($b = 0; $b < $bucketCount; $b++) {
                $isLow = $lowStockParts->contains('id', $part->id);
                $items->push(
                    InventoryItem::factory()
                        ->when($isLow, fn ($f) => $f->lowStock())
                        ->create([
                            'workshop_id' => $workshop->id,
                            'part_id' => $part->id,
                            'bin_id' => $bins->random()->id,
                        ])
                );
            }
        }

        // ---- Recent stock movements ledger ----
        StockMovement::factory()
            ->count(40)
            ->state(fn () => [
                'workshop_id' => $workshop->id,
                'part_id' => $parts->random()->id,
                'bin_id' => $bins->random()->id,
            ])
            ->create();

        // ---- Suppliers + purchase orders ----
        $suppliers = Supplier::factory()
            ->count(4)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();

        $purchaseOrders = collect();
        foreach ($suppliers->take(3) as $supplier) {
            $po = PurchaseOrder::factory()
                ->approved()
                ->create([
                    'workshop_id' => $workshop->id,
                    'supplier_id' => $supplier->id,
                    'created_by' => $mechanics->first()->id,
                ]);

            PurchaseOrderItem::factory()
                ->count(3)
                ->state(fn () => [
                    'purchase_order_id' => $po->id,
                    'part_id' => $parts->random()->id,
                ])
                ->create();

            $purchaseOrders->push($po);
        }

        // One draft PO so admins can preview the draft state
        $draftPo = PurchaseOrder::factory()->draft()->create([
            'workshop_id' => $workshop->id,
            'supplier_id' => $suppliers->last()->id,
            'created_by' => $mechanics->first()->id,
        ]);
        PurchaseOrderItem::factory()->count(2)->create([
            'purchase_order_id' => $draftPo->id,
            'part_id' => $parts->random()->id,
        ]);

        // ---- Tools + checkouts ----
        $tools = Tool::factory()
            ->count(8)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();

        ToolCheckout::factory()
            ->count(5)
            ->state(fn () => [
                'workshop_id' => $workshop->id,
                'tool_id' => $tools->random()->id,
                'user_id' => $mechanics->random()->id,
            ])
            ->create();

        // ---- Open job cards + parts attached ----
        $openJobs = JobCard::factory()
            ->count(4)
            ->state(fn () => [
                'workshop_id' => $workshop->id,
                'mechanic_id' => $mechanics->random()->id,
                'status' => 'open',
            ])
            ->create();

        foreach ($openJobs as $job) {
            JobCardPart::factory()
                ->count(3)
                ->state(fn () => [
                    'workshop_id' => $workshop->id,
                    'job_card_id' => $job->id,
                    'part_id' => $parts->random()->id,
                    'issued_by' => $mechanics->first()->id,
                ])
                ->create();
        }

        // ---- Audit log entries ----
        AuditLog::factory()
            ->count(15)
            ->state(fn () => ['workshop_id' => $workshop->id])
            ->create();
    }
}
