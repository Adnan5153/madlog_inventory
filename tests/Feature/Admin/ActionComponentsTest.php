<?php

namespace Tests\Feature\Admin;

use App\Models\Part;
use App\Models\PartCategory;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the reusable action Blade components emit the right markup:
 *   - <x-admin.actions.view>    -> <a class="btn btn-sm btn-outline-secondary">
 *   - <x-admin.actions.edit>    -> delegates to .view with default icon=bi-pencil
 *   - <x-admin.actions.delete>  -> <form> with @csrf + @method('DELETE'),
 *                                  data-confirm-form + data-confirm attributes,
 *                                  <button class="btn btn-sm btn-outline-danger">
 *
 * Exercises the components via real page renders (the same code path the
 * user hits in the browser) rather than Blade::renderComponent, so the
 * test catches regressions in route resolution, CSRF token middleware,
 * attribute merging, and the data-confirm-form wiring in one shot.
 */
class ActionComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Workshop $workshop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $this->workshop = Workshop::factory()->create();

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => $this->workshop->id,
        ]);
    }

    public function test_view_component_emits_icon_only_button_with_href(): void
    {
        // Warehouses' row template uses <x-admin.actions.view> WITHOUT a label,
        // so the show anchor is icon-only. This is the closest pure view test
        // (the products test below also exercises view, but inline).
        Workshop::factory()->create(['name' => 'Main warehouse']);

        $response = $this->actingAs($this->admin)->get('/admin/warehouses');

        $response->assertOk();

        $response->assertSee('bi-eye', false);
        $response->assertSee('btn btn-sm btn-outline-secondary', false);
        $response->assertSee('href="http://localhost:8000/admin/warehouses/', false);
    }

    public function test_edit_component_is_icon_only(): void
    {
        PartCategory::factory()->count(2)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/categories');

        $response->assertOk();
        $content = $response->getContent();

        // Edit anchor: <a>...pencil icon...</a> with no text node after the icon.
        preg_match_all(
            '#<a\b[^>]*class="btn btn-sm btn-outline-secondary"[^>]*>(.*?)</a>#s',
            $content,
            $matches,
        );

        $iconOnlyCount = 0;
        foreach ($matches[1] ?? [] as $body) {
            $cleaned = preg_replace('#\s*<i[^>]*class="bi bi-pencil"[^>]*></i>\s*#', '', $body);
            if (trim($cleaned) === '') {
                $iconOnlyCount++;
            }
        }

        $this->assertGreaterThan(
            0,
            $iconOnlyCount,
            'Expected at least one icon-only edit anchor on the categories index.',
        );
    }

    public function test_delete_component_emits_csrf_method_and_confirm(): void
    {
        $category = PartCategory::factory()->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/categories');

        $response->assertOk();
        $content = $response->getContent();

        // CSRF token present in the form
        $this->assertMatchesRegularExpression(
            '/<form[^>]*action="'.preg_quote(route('admin.categories.destroy', $category), '/').'"[^>]*>.*?_token.*?<\/form>/s',
            $content,
        );

        // DELETE method spoofing
        $this->assertStringContainsString('name="_method" value="DELETE"', $content);

        // data-confirm attributes required by the JS confirm handler
        $this->assertStringContainsString('data-confirm-form', $content);
        $this->assertStringContainsString('data-confirm="Delete this category? Parts in it must be moved first."', $content);

        // Outline-danger button with trash icon
        $this->assertStringContainsString('btn btn-sm btn-outline-danger', $content);
        $this->assertStringContainsString('bi bi-trash', $content);
    }

    public function test_delete_component_supports_icon_override(): void
    {
        // Warehouses are modelled via the Workshop table. Create a warehouse
        // and a workshop-less super-admin so the row template's edit/delete
        // gate (`$user?->workshop_id === null`) opens up.
        Workshop::factory()->create();

        $superAdmin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'workshop_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)->get('/admin/warehouses');

        $response->assertOk();

        $content = $response->getContent();

        // bi-archive override applied.
        $this->assertStringContainsString('bi bi-archive', $content);
        $this->assertStringContainsString(
            'data-confirm="Archive this warehouse? Records stay in the database."',
            $content,
        );
    }

    public function test_view_component_renders_icon_only_when_label_is_null(): void
    {
        // Products row uses <x-admin.actions.view> WITHOUT a label prop,
        // so the anchor must contain the eye icon and NOT a "View" text node.
        Part::factory()->count(2)->create(['workshop_id' => $this->workshop->id]);

        $response = $this->actingAs($this->admin)->get('/admin/products');

        $response->assertOk();
        $content = $response->getContent();

        // Find any <a class="...btn-outline-secondary"...>...bi-eye...</a> sequence
        // and assert the body has no text node after the icon (no "View", "Edit", etc).
        preg_match_all(
            '#<a\b[^>]*class="btn btn-sm btn-outline-secondary"[^>]*>(.*?)</a>#s',
            $content,
            $matches,
        );

        $iconOnlyCount = 0;
        foreach ($matches[1] ?? [] as $body) {
            // Strip whitespace and the icon; whatever remains must be empty.
            $cleaned = preg_replace('#\s*<i[^>]*class="bi bi-eye"[^>]*></i>\s*#', '', $body);
            if (trim($cleaned) === '') {
                $iconOnlyCount++;
            }
        }

        $this->assertGreaterThan(
            0,
            $iconOnlyCount,
            'Expected at least one icon-only view anchor (no label) on the products index.',
        );
    }
}
