<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\BinLocation;
use App\Models\InventoryItem;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\User;
use App\Models\Workshop;
use App\Scopes\WorkshopScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for Phase 3 (warehousing + audit + notifications):
 * warehouse CRUD, bin-location CRUD, audit observer behaviour,
 * audit log list/export, low-stock event.
 */
class WarehousingAndAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $globalAdmin;
    protected Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->globalAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null, // global
        ]);
    }

    public function test_global_admin_can_create_warehouse(): void
    {
        $this->actingAs($this->globalAdmin)
            ->post('/admin/warehouses', [
                'name' => 'New Warehouse',
                'slug' => 'new-warehouse',
                'is_active' => true,
            ])
            ->assertRedirect('/admin/warehouses');

        $this->assertDatabaseHas('workshops', [
            'name' => 'New Warehouse',
            'slug' => 'new-warehouse',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'workshop.created',
        ]);
    }

    public function test_workshop_scoped_admin_cannot_create_warehouse(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/warehouses', [
                'name' => 'Forbidden',
                'is_active' => true,
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_list_warehouses(): void
    {
        Workshop::factory()->count(3)->create();

        $this->actingAs($this->globalAdmin)
            ->get('/admin/warehouses')
            ->assertOk()
            ->assertSee('Warehouses');
    }

    public function test_warehouse_can_be_soft_deleted(): void
    {
        $w = Workshop::factory()->create();

        $this->actingAs($this->globalAdmin)
            ->delete("/admin/warehouses/{$w->id}")
            ->assertRedirect('/admin/warehouses');

        $this->assertSoftDeleted('workshops', ['id' => $w->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'workshop.deleted',
        ]);
    }

    public function test_admin_can_crud_bin_location(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/bin-locations', [
                'code' => 'A-12',
                'zone' => 'Brakes',
                'aisle' => 'A',
                'shelf' => '1',
                'is_active' => true,
            ])
            ->assertRedirect('/admin/bin-locations');

        $this->assertDatabaseHas('bin_locations', [
            'workshop_id' => $this->workshop->id,
            'code' => 'A-12',
            'zone' => 'Brakes',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'bin_location.created',
        ]);
    }

    public function test_bin_code_is_unique_per_workshop(): void
    {
        BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
            'code' => 'B-1',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/bin-locations', [
                'code' => 'B-1',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_bin_with_inventory_cannot_be_deleted(): void
    {
        $bin = BinLocation::factory()->create([
            'workshop_id' => $this->workshop->id,
            'code' => 'C-1',
        ]);
        $part = Part::factory()->create(['workshop_id' => $this->workshop->id]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'bin_id' => $bin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/bin-locations/{$bin->id}")
            ->assertRedirect()
            ->assertSessionHasErrors('bin');

        $this->assertDatabaseHas('bin_locations', ['id' => $bin->id]);
    }

    public function test_audit_observer_records_creation(): void
    {
        PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Audit Test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'part_category.created',
        ]);
    }

    public function test_audit_observer_records_update_with_diff(): void
    {
        $cat = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Old Name',
        ]);

        $cat->update(['name' => 'New Name']);

        $log = WorkshopScope::disabled(function () use ($cat) {
            return AuditLog::query()
                ->where('action', 'part_category.updated')
                ->where('subject_id', $cat->id)
                ->latest()
                ->first();
        });
        $this->assertNotNull($log);
        $this->assertSame('Old Name', $log->changes['before']['name'] ?? null);
        $this->assertSame('New Name', $log->changes['after']['name'] ?? null);
    }

    public function test_audit_log_index_renders(): void
    {
        AuditLog::factory()->count(3)->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertSee('Audit logs');
    }

    public function test_audit_log_show_renders_changes(): void
    {
        $cat = PartCategory::factory()->create(['workshop_id' => $this->workshop->id]);
        $cat->update(['name' => 'Updated']);

        $log = WorkshopScope::disabled(function () use ($cat) {
            return AuditLog::query()
                ->where('action', 'part_category.updated')
                ->where('subject_id', $cat->id)
                ->latest()
                ->first();
        });

        $this->actingAs($this->admin)
            ->get("/admin/audit-logs/{$log->id}")
            ->assertOk()
            ->assertSee('Updated');
    }

    public function test_audit_log_export_returns_csv(): void
    {
        AuditLog::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/audit-logs-export');
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_audit_logs_cannot_be_updated(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_logs_cannot_be_deleted(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_low_stock_event_fires_when_threshold_crossed(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\InventoryLowStockReached::class]);

        $part = Part::factory()->create([
            'workshop_id' => $this->workshop->id,
            'reorder_threshold' => 5,
        ]);
        InventoryItem::factory()->create([
            'workshop_id' => $this->workshop->id,
            'part_id' => $part->id,
            'quantity' => 1,
        ]);
        $part->touch(); // trigger saved observer

        \Illuminate\Support\Facades\Event::assertDispatched(
            \App\Events\InventoryLowStockReached::class,
            function ($event) use ($part) {
                return $event->part->id === $part->id && $event->threshold === 5;
            }
        );
    }
}