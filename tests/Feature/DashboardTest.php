<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_admins_are_redirected_to_the_admin_dashboard(): void
    {
        $ws = Workshop::factory()->create();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $ws->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_global_admins_are_redirected_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_staff_are_redirected_to_the_staff_dashboard(): void
    {
        $ws = Workshop::factory()->create();
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'workshop_id' => $ws->id,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertRedirect(route('staff.dashboard'));
    }
}
