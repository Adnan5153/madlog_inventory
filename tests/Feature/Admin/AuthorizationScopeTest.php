<?php

namespace Tests\Feature\Admin;

use App\Models\Part;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-tenant isolation guards.
 *
 * Workshop A's admin must not be able to read or mutate Workshop B's
 * records via the admin surface. This protects the BelongsToWorkshop
 * + WorkshopScope invariant that the entire module relies on.
 */
class AuthorizationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Workshop $workshopA;
    protected Workshop $workshopB;
    protected User $adminA;
    protected User $adminB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->workshopA = Workshop::factory()->create(['name' => 'A']);
        $this->workshopB = Workshop::factory()->create(['name' => 'B']);

        $this->adminA = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshopA->id,
        ]);
        $this->adminB = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshopB->id,
        ]);
    }

    public function test_workshop_a_admin_cannot_view_a_workshop_b_part(): void
    {
        $partB = Part::factory()->create(['workshop_id' => $this->workshopB->id]);

        // Try to access workshop B's part by URL — should 404 because
        // the model is hidden by WorkshopScope.
        $this->actingAs($this->adminA)
            ->get("/admin/products/{$partB->id}/edit")
            ->assertNotFound();
    }

    public function test_workshop_a_admin_cannot_update_a_workshop_b_part(): void
    {
        $partB = Part::factory()->create([
            'workshop_id' => $this->workshopB->id,
            'name' => 'B only',
        ]);

        $this->actingAs($this->adminA)
            ->put("/admin/products/{$partB->id}", [
                'name' => 'Hijacked',
                'sku'  => $partB->sku,
            ])
            ->assertNotFound();

        $this->assertSame('B only', $partB->fresh()->name);
    }

    public function test_global_admin_can_access_both_workshops(): void
    {
        $globalAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);

        $partA = Part::factory()->create(['workshop_id' => $this->workshopA->id]);
        $partB = Part::factory()->create(['workshop_id' => $this->workshopB->id]);

        // Edit pages for both workshops should resolve for the global admin.
        $this->actingAs($globalAdmin)
            ->get("/admin/products/{$partA->id}/edit")
            ->assertOk();

        $this->actingAs($globalAdmin)
            ->get("/admin/products/{$partB->id}/edit")
            ->assertOk();
    }

    public function test_admin_cannot_view_users_in_another_workshop(): void
    {
        $userInB = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshopB->id,
        ]);

        $this->actingAs($this->adminA)
            ->get("/admin/users/{$userInB->id}/edit")
            ->assertForbidden();
    }

    public function test_admin_can_view_users_in_own_workshop(): void
    {
        $userInA = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $this->workshopA->id,
        ]);

        $this->actingAs($this->adminA)
            ->get("/admin/users/{$userInA->id}/edit")
            ->assertOk();
    }
}