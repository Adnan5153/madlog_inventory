<?php

namespace Tests\Feature\Admin;

use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use App\Models\BinLocation;
use App\Models\Supplier;
use App\Models\Tool;
use App\Models\ToolCategory;
use App\Models\ToolCheckout;
use App\Models\ToolMaintenanceRecord;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke tests for the Tools Management Module: master CRUD, search/filter,
 * cross-tenant isolation, audit log + soft-delete + referenced records.
 */
class ToolModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected Workshop $workshop;

    protected Workshop $otherWorkshop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();
        $this->otherWorkshop = Workshop::factory()->create();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);

        $this->staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshop->id,
        ]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'tool_code' => 'TL-1001',
            'name' => 'Torque Wrench 1/2"',
            'brand' => 'Snap-on',
            'model' => 'TQ-100',
            'serial_number' => 'SN1001',
            'barcode' => '1000000000001',
            'qr_code' => 'QR-1001',
            'description' => 'Click-type torque wrench',
            'condition' => ToolCondition::Good->value,
            'status' => ToolStatus::Available->value,
            'is_active' => true,
            'purchase_price' => 250.00,
        ], $overrides);
    }

    public function test_index_renders_for_authorized_user(): void
    {
        Tool::factory()->count(3)->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->get('/admin/tools')
            ->assertOk()
            ->assertSee('Tools');
    }

    public function test_index_renders_for_staff_workshop_member(): void
    {
        $this->actingAs($this->staff)
            ->get('/admin/tools')
            ->assertOk();
    }

    public function test_dashboard_renders_for_authorized_user(): void
    {
        Tool::factory()->count(2)->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->get('/admin/tools/dashboard')
            ->assertOk()
            ->assertSee('Tools dashboard');
    }

    public function test_admin_can_create_tool_and_writes_audit_log(): void
    {
        $supplier = Supplier::factory()->create(['workshop_id' => $this->workshop->id]);
        $bin = BinLocation::factory()->create(['workshop_id' => $this->workshop->id]);
        $category = ToolCategory::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->post('/admin/tools', $this->validPayload([
                'supplier_id' => $supplier->id,
                'bin_id' => $bin->id,
                'category_id' => $category->id,
            ]))
            ->assertRedirect('/admin/tools');

        $this->assertDatabaseHas('tools', [
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-1001',
            'name' => 'Torque Wrench 1/2"',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tool.created',
            'subject_type' => Tool::class,
        ]);
    }

    public function test_create_rejects_missing_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/tools', [])
            ->assertSessionHasErrors([
                'tool_code',
                'name',
                'condition',
                'status',
                'is_active',
            ]);
    }

    public function test_create_rejects_duplicate_tool_code_within_workshop(): void
    {
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-DUP',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/tools', $this->validPayload(['tool_code' => 'TL-DUP']))
            ->assertSessionHasErrors('tool_code');
    }

    public function test_create_rejects_duplicate_serial_within_workshop(): void
    {
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'serial_number' => 'SNDUP',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/tools', $this->validPayload([
                'tool_code' => 'TL-OTHER',
                'serial_number' => 'SNDUP',
            ]))
            ->assertSessionHasErrors('serial_number');
    }

    public function test_create_rejects_duplicate_barcode_within_workshop(): void
    {
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'barcode' => '9999999999999',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/tools', $this->validPayload([
                'tool_code' => 'TL-OTHER',
                'serial_number' => 'SN-OTHER',
                'barcode' => '9999999999999',
            ]))
            ->assertSessionHasErrors('barcode');
    }

    public function test_admin_can_update_tool_and_audit_records_diff(): void
    {
        $tool = Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Old name',
        ]);

        $this->actingAs($this->admin)
            ->put("/admin/tools/{$tool->id}", $this->validPayload(['name' => 'New name']))
            ->assertRedirect('/admin/tools');

        $this->assertDatabaseHas('tools', [
            'id' => $tool->id,
            'name' => 'New name',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tool.updated',
            'subject_type' => Tool::class,
            'subject_id' => $tool->id,
        ]);
    }

    public function test_delete_blocked_when_checkouts_or_maintenance_exist(): void
    {
        $tool = Tool::factory()->create(['workshop_id' => $this->workshop->id]);
        ToolCheckout::factory()->forUser($this->staff)->closed()->create([
            'workshop_id' => $this->workshop->id,
            'tool_id' => $tool->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/tools/{$tool->id}")
            ->assertSessionHasErrors('tool');

        $this->assertDatabaseHas('tools', ['id' => $tool->id, 'deleted_at' => null]);
    }

    public function test_admin_can_delete_tool_with_no_history(): void
    {
        $tool = Tool::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->delete("/admin/tools/{$tool->id}")
            ->assertRedirect('/admin/tools');

        $this->assertSoftDeleted('tools', ['id' => $tool->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tool.deleted',
            'subject_type' => Tool::class,
            'subject_id' => $tool->id,
        ]);
    }

    public function test_search_finds_tool_by_name_or_code(): void
    {
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-FIND',
            'name' => 'Torque Wrench 1/2"',
        ]);
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-OTHER',
            'name' => 'Pneumatic Impact Gun',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/tools?q=torque')
            ->assertOk()
            ->assertSee('Torque Wrench 1/2"')
            ->assertDontSee('Pneumatic Impact Gun');
    }

    public function test_filter_by_status(): void
    {
        Tool::factory()->available()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-AVAIL',
        ]);
        Tool::factory()->underMaintenance()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-MAINT',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/tools?status='.ToolStatus::UnderMaintenance->value)
            ->assertOk()
            ->assertSee('TL-MAINT')
            ->assertDontSee('TL-AVAIL');
    }

    public function test_filter_by_category(): void
    {
        $cat = ToolCategory::factory()->create(['workshop_id' => $this->workshop->id]);
        Tool::factory()->forCategory($cat)->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-CAT',
        ]);
        Tool::factory()->create([
            'workshop_id' => $this->workshop->id,
            'tool_code' => 'TL-NOCAT',
        ]);

        $this->actingAs($this->admin)
            ->get('/admin/tools?category_id='.$cat->id)
            ->assertOk()
            ->assertSee('TL-CAT')
            ->assertDontSee('TL-NOCAT');
    }

    public function test_pagination_preserves_filters(): void
    {
        Tool::factory()->count(25)->available()->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/tools?status='.ToolStatus::Available->value.'&page=2')
            ->assertOk();

        // Pagination markup should preserve the status filter on the
        // "previous" / "next" links.
        $response->assertSee('status='.ToolStatus::Available->value);
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTool = Tool::factory()->create(['workshop_id' => $this->otherWorkshop->id]);

        $this->actingAs($this->admin)
            ->get("/admin/tools/{$otherTool->id}")
            ->assertNotFound();
    }

    public function test_tool_category_create_blocks_duplicates_within_workshop(): void
    {
        ToolCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Hand Tools',
        ]);

        $this->actingAs($this->admin)
            ->post('/admin/tool-categories', [
                'workshop_id' => $this->workshop->id,
                'name' => 'Hand Tools',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_tool_category_delete_blocked_when_tools_exist(): void
    {
        $cat = ToolCategory::factory()->create(['workshop_id' => $this->workshop->id]);
        Tool::factory()->forCategory($cat)->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/tool-categories/{$cat->id}")
            ->assertSessionHasErrors('category');
    }

    public function test_staff_cannot_create_or_delete_tool(): void
    {
        $response = $this->actingAs($this->staff)->post('/admin/tools', $this->validPayload());

        // Either forbidden (403) or redirect with errors (302) is fine —
        // the staff user must NOT have created a tool.
        $this->assertContains($response->getStatusCode(), [302, 403]);
        $this->assertDatabaseMissing('tools', ['tool_code' => 'TL-1001']);

        $tool = Tool::factory()->create(['workshop_id' => $this->workshop->id]);
        $deleteResponse = $this->actingAs($this->staff)->delete("/admin/tools/{$tool->id}");
        $this->assertContains($deleteResponse->getStatusCode(), [302, 403]);

        $this->assertDatabaseHas('tools', ['id' => $tool->id, 'deleted_at' => null]);
    }

    public function test_staff_can_checkout_and_checkin_tool(): void
    {
        $tool = Tool::factory()->available()->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/tools/{$tool->id}/checkout", [
                'user_id' => $this->staff->id,
                'purpose' => 'Job #99',
            ])
            ->assertRedirect("/admin/tools/{$tool->id}");

        $tool->refresh();
        $this->assertSame(ToolStatus::CheckedOut, $tool->status);
        $this->assertSame($this->staff->id, $tool->current_holder_user_id);

        $this->actingAs($this->staff)
            ->post("/admin/tools/{$tool->id}/checkin", [
                'received_by' => $this->admin->id,
                'condition_at_return' => ToolCondition::Good->value,
            ])
            ->assertRedirect("/admin/tools/{$tool->id}");

        $tool->refresh();
        $this->assertSame(ToolStatus::Available, $tool->status);
        $this->assertNull($tool->current_holder_user_id);
    }

    public function test_checkout_blocked_when_already_checked_out(): void
    {
        $tool = Tool::factory()->available()->create([
            'workshop_id' => $this->workshop->id,
        ]);
        ToolCheckout::factory()->open()->forUser($this->staff)->create([
            'workshop_id' => $this->workshop->id,
            'tool_id' => $tool->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/tools/{$tool->id}/checkout", [
                'user_id' => $this->staff->id,
            ])
            ->assertSessionHasErrors('tool_id');
    }

    public function test_checkout_blocked_when_tool_under_maintenance(): void
    {
        $tool = Tool::factory()->underMaintenance()->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $this->actingAs($this->staff)
            ->post("/admin/tools/{$tool->id}/checkout", [
                'user_id' => $this->staff->id,
            ])
            ->assertSessionHasErrors('tool_id');
    }

    public function test_maintenance_record_creation_writes_audit_and_due_date(): void
    {
        $tool = Tool::factory()->create(['workshop_id' => $this->workshop->id]);

        $this->actingAs($this->admin)
            ->post("/admin/tools/{$tool->id}/maintenance", [
                'type' => 'preventive',
                'performed_by' => $this->admin->id,
                'vendor' => 'Snap-on Industrial',
                'cost' => 120.00,
                'performed_at' => now()->format('Y-m-d'),
                'next_due_at' => now()->addMonths(3)->format('Y-m-d'),
                'description' => 'Annual calibration and cleaning',
            ])
            ->assertRedirect("/admin/tools/{$tool->id}/maintenance");

        $this->assertDatabaseHas('tool_maintenance_records', [
            'tool_id' => $tool->id,
            'workshop_id' => $this->workshop->id,
            'type' => 'preventive',
            'vendor' => 'Snap-on Industrial',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tool.maintenance_recorded',
            'subject_type' => Tool::class,
            'subject_id' => $tool->id,
        ]);
    }

    public function test_maintenance_record_delete_blocked_when_due_in_past(): void
    {
        $tool = Tool::factory()->create(['workshop_id' => $this->workshop->id]);
        $record = ToolMaintenanceRecord::factory()->overdue()->create([
            'workshop_id' => $this->workshop->id,
            'tool_id' => $tool->id,
        ]);

        $this->actingAs($this->admin)
            ->delete("/admin/tools/{$tool->id}/maintenance/{$record->id}")
            ->assertSessionHasErrors('record');
    }
}
