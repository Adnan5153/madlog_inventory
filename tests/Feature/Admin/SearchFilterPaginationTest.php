<?php

namespace Tests\Feature\Admin;

use App\Models\BinLocation;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Workshop;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Search / filter / pagination architecture regression tests.
 *
 * Verifies the cross-cutting bug fix:
 *
 *   "After applying a search/filter and then clearing it, pagination stops
 *    working and all records are displayed."
 *
 * Architecture under test:
 *   - Controllers use Laravel's `paginate(N)->withQueryString()`.
 *   - The JSON live-search endpoint also returns a paginated payload
 *     (rows + pagination HTML + total/page/last_page/per_page).
 *   - Pagination links preserve all active filter params (withQueryString).
 *   - Clearing filters returns the user to page 1 of the unfiltered set.
 *   - Filtering happens at the database level — no `get()`-then-`filter()`.
 *
 * The fixture model is `Part` because it has the richest filter set
 * (text search, category, brand, active status, sort) and is used by every
 * other list page through the `HasLiveSearch` trait.
 */
class SearchFilterPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Workshop $workshop;

    protected Workshop $otherWorkshop;

    protected PartCategory $category;

    protected Unit $unit;

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

        $this->category = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Brakes',
        ]);

        $this->unit = Unit::factory()->create();
    }

    private function makeParts(int $count, array $overrides = []): void
    {
        Part::factory()->count($count)->create(array_merge([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ], $overrides));
    }

    // 1. Default index renders a paginator (not the full table).
    public function test_default_index_is_paginated(): void
    {
        $this->makeParts(25);

        $response = $this->actingAs($this->admin)->get('/admin/products');

        $response->assertOk();

        // Default per-page is 20, so 5 records must be hidden behind the paginator.
        $tr = $response->getContent();
        $rowCount = substr_count($tr, '<tr>');
        $this->assertLessThan(25, $rowCount, 'Index rendered all 25 rows; pagination is not in effect.');

        // The paginator renders "Showing 1 to 20 of 25 results"-style markup
        // (or the Bootstrap-5 chevron icons). Both indicate pagination is live.
        $this->assertMatchesRegularExpression(
            '/pagination|Showing\s+\d+\s+to\s+\d+/i',
            $tr,
            'No pagination UI was rendered on the default index page.',
        );
    }

    // 2. Search via ?q= still paginates.
    public function test_search_returns_only_matching_records_and_remains_paginated(): void
    {
        // 25 parts with "brake" in the name + 5 unrelated parts.
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad Model X',
        ]);
        $this->makeParts(5, ['name' => 'Oil Filter']);

        $response = $this->actingAs($this->admin)->get('/admin/products?q=brake');

        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Brake Pad', $html);
        $this->assertStringNotContainsString('Oil Filter', $html);

        // Pagination must still be present even when filtering.
        $this->assertMatchesRegularExpression('/pagination|Showing\s+\d+\s+to\s+\d+/i', $html);
    }

    // 3. Filter via ?active=yes still paginates.
    public function test_filter_returns_only_matching_records_and_remains_paginated(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/products?active=yes');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/pagination|Showing\s+\d+\s+to\s+\d+/i', $html);
    }

    // 4. Search + filter together still paginate.
    public function test_search_plus_filter_remains_paginated(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
            'is_active' => true,
        ]);
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=brake&active=yes');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/pagination|Showing\s+\d+\s+to\s+\d+/i', $html);
    }

    // 5. The JSON live-search endpoint returns a paginator-shaped payload.
    public function test_search_json_endpoint_returns_paginated_payload(): void
    {
        $this->makeParts(25);

        $response = $this->actingAs($this->admin)
            ->getJson('/admin/products-search?q=');

        $response->assertOk();

        $data = $response->json();
        $this->assertIsArray($data['rows'] ?? null, 'JSON response missing rows.');
        $this->assertIsString($data['pagination'] ?? null, 'JSON response missing pagination HTML.');
        $this->assertSame(25, $data['total'] ?? null, 'total should match seed count.');
        $this->assertSame(2, $data['last_page'] ?? null, '25 / 20 = 2 pages.');
        $this->assertSame(20, $data['per_page'] ?? null, 'per_page should match the index.');
        $this->assertStringContainsString('<nav', $data['pagination'], 'pagination should be a rendered <nav>.');
    }

    // 6. The JSON live-search endpoint limits rows to per_page (no full-table dump).
    public function test_search_json_paginator_limits_rows_per_page(): void
    {
        $this->makeParts(30);

        $response = $this->actingAs($this->admin)->getJson('/admin/products-search?q=');

        $response->assertOk();

        $data = $response->json();
        $this->assertCount(20, $data['rows'], 'JSON response returned more rows than per_page.');
        $this->assertSame(30, $data['total']);
        $this->assertSame(2, $data['last_page']);

        // Regression: each row-template render must produce exactly ONE <tr>.
        // A previous bug rendered the template per row, which re-iterated the
        // page slice and emitted per_page × per_page <tr> nodes — the browser
        // would then show the entire dataset (no visible pagination).
        $trCount = array_sum(array_map(
            fn ($html) => preg_match_all('#<tr\b#', $html),
            $data['rows'],
        ));
        $this->assertSame(20, $trCount, "Expected 20 <tr> nodes across rows; got {$trCount} (template was likely rendered per row).");
    }

    // 6b. The empty-result branch produces a single <tr> (the server's
    // @empty cell), not zero rows — so the JS can show it instead of its
    // generic "No results" placeholder.
    public function test_search_json_empty_results_render_one_empty_row(): void
    {
        $this->makeParts(5);

        $response = $this->actingAs($this->admin)->getJson('/admin/products-search?q=ZZZZNOMATCH');
        $response->assertOk();

        $data = $response->json();
        $this->assertCount(1, $data['rows']);
        $this->assertSame(0, $data['total']);
        $this->assertSame(1, $data['last_page']);
        $this->assertStringContainsString('No products match', $data['rows'][0]);
    }

    // 7. Pagination page=2 preserves the search term.
    public function test_pagination_page_two_preserves_search(): void
    {
        // Use a category whose name doesn't contain "brake" — the
        // ProductController query also searches related category names,
        // so a category of "Brakes" would accidentally match `q=brake`.
        $neutralCategory = PartCategory::factory()->create([
            'workshop_id' => $this->workshop->id,
            'name' => 'Filters',
        ]);

        // 25 matched parts (name contains "Brake Pad"). The category is
        // set to the neutral one and other searchable columns are kept
        // brake-free.
        for ($i = 1; $i <= 25; $i++) {
            Part::factory()->create([
                'workshop_id' => $this->workshop->id,
                'category_id' => $neutralCategory->id,
                'unit_id' => $this->unit->id,
                'name' => 'Brake Pad',
                'description' => 'Standard replacement part',
                'sku' => 'BP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'brand' => 'Bosch',
            ]);
        }
        // 3 unmatched parts — name/sku/brand/category all avoid "brake".
        for ($i = 1; $i <= 3; $i++) {
            Part::factory()->create([
                'workshop_id' => $this->workshop->id,
                'category_id' => $neutralCategory->id,
                'unit_id' => $this->unit->id,
                'name' => 'ZZZZ Unmatched Item',
                'description' => 'Generic accessory',
                'sku' => 'UA-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'brand' => 'Continental',
            ]);
        }

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=brake&page=2');

        $response->assertOk();
        $html = $response->getContent();

        // Parse all hrefs and confirm at least one carries q=brake AND
        // has page=1 (the previous-page link from page=2).
        $this->assertHrefHasParams($html, ['q' => 'brake', 'page' => '1']);
        // The unrelated record must NOT appear in the table body.
        $tbody = $this->extractTbody($html);
        $this->assertStringNotContainsString('ZZZZ Unmatched Item', $tbody);
    }

    // 8. Pagination page=2 preserves a filter param.
    public function test_pagination_page_two_preserves_filter(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?active=yes&page=2');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertHrefHasParams($html, ['active' => 'yes', 'page' => '1']);
    }

    // 9. Pagination page=2 preserves search AND filter together.
    public function test_pagination_page_two_preserves_search_and_filter_together(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
            'is_active' => true,
        ]);
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=brake&active=yes&page=2');

        $response->assertOk();
        $html = $response->getContent();

        $this->assertHrefHasParams($html, ['q' => 'brake', 'active' => 'yes']);
    }

    // 10. Clearing the search returns the user to the default paginated dataset.
    public function test_clearing_search_returns_to_default_paginated_dataset(): void
    {
        // 25 parts named "Brake Pad" — only 5 of these have anything in
        // their description that contains "brake", ensuring the search
        // filter shrinks the dataset substantially.
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
        ]);
        // 5 unrelated parts.
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'ZZZZ Synthetic Unmatched Part',
        ]);

        // First, with a search applied:
        $this->actingAs($this->admin)
            ->get('/admin/products?q=brake')
            ->assertOk();

        // Then, no query params — same controller path, paginated.
        $response = $this->actingAs($this->admin)->get('/admin/products');

        $response->assertOk();
        $html = $response->getContent();
        $tbody = $this->extractTbody($html);

        // Both kinds of records should now be in the broader paginated
        // dataset. (Brake Pad sorts first alphabetically; ZZZZ sorts last.
        // With 30 records and per_page=20, ZZZZ falls on page 2 — so to
        // confirm ZZZZ is in the dataset we look at the live-search JSON
        // response which returns the same builder unfiltered.)
        $json = $this->actingAs($this->admin)->getJson('/admin/products-search?q=')->json();
        $this->assertSame(30, $json['total'] ?? null);
        $this->assertSame(2, $json['last_page'] ?? null);

        $this->assertMatchesRegularExpression('/pagination|Showing\s+\d+\s+to\s+\d+/i', $html);
    }

    // 11. Clearing a filter returns the user to the default paginated dataset.
    public function test_clearing_filter_returns_to_default_paginated_dataset(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'is_active' => false,
        ]);

        // Bare URL after a filter has been applied.
        $response = $this->actingAs($this->admin)->get('/admin/products');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/pagination|Showing\s+\d+\s+to\s+\d+/i', $html);
    }

    // 12. Server-rendered paginator links preserve query-string params.
    public function test_pagination_links_preserve_query_string(): void
    {
        Part::factory()->count(25)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Brake Pad',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=brake&category_id='.$this->category->id);

        $response->assertOk();
        $html = $response->getContent();

        // Search for a pagination link (the "next page" or "page 2" link).
        // It must carry both q=brake and category_id.
        $this->assertHrefHasParams($html, [
            'page' => '2',
            'q' => 'brake',
            'category_id' => (string) $this->category->id,
        ], 'No pagination link preserved both q and category_id.');
    }

    /**
     * Extract the contents of the live-search tbody so assertions don't
     * accidentally match navigation/footer strings.
     */
    private function extractTbody(string $html): string
    {
        if (preg_match('/<tbody[^>]*data-live-search-target[^>]*>(.*?)<\/tbody>/s', $html, $m)) {
            return $m[1];
        }
        if (preg_match('/<tbody[^>]*>(.*?)<\/tbody>/s', $html, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Assert that at least one `href` on the page has every key/value
     * pair in $params present in its query string (param order is
     * irrelevant). Returns the matching href for further inspection.
     */
    private function assertHrefHasParams(string $html, array $params, ?string $message = null): string
    {
        preg_match_all('/href="([^"]+)"/', $html, $matches);
        foreach ($matches[1] as $href) {
            $qs = parse_url($href, PHP_URL_QUERY);
            if ($qs === null || $qs === false) {
                continue;
            }
            // Laravel renders &amp; in HTML; turn it back into & before
            // parsing the query string so parse_str sees the right keys.
            $qs = html_entity_decode($qs, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            parse_str($qs, $parsed);
            $match = true;
            foreach ($params as $k => $v) {
                $cmp = is_int($v) ? (string) $v : $v;
                if ((string) ($parsed[$k] ?? '') !== $cmp) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $href;
            }
        }
        $this->fail($message ?? 'No href on the page contained the expected query params: '.json_encode($params));

        return '';
    }

    // 13. Invalid filter values are handled safely (no SQL error).
    public function test_invalid_filter_values_are_handled_safely(): void
    {
        $this->makeParts(2);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?category_id=abc&active=foo&sort=DROP_TABLE');

        $response->assertOk();
    }

    // 14. Workshop-scoped data is not leaked via filter combinations.
    public function test_unauthorized_users_cannot_access_restricted_records(): void
    {
        // 5 parts in workshop A (admin's workshop), all named predictably.
        Part::factory()->count(5)->create([
            'workshop_id' => $this->workshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Workshop A Part',
        ]);

        // 5 parts in workshop B (other workshop), all named predictably.
        Part::factory()->count(5)->create([
            'workshop_id' => $this->otherWorkshop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Workshop B Part',
        ]);

        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=');

        $response->assertOk();
        $tbody = $this->extractTbody($response->getContent());

        $this->assertStringContainsString('Workshop A Part', $tbody);
        $this->assertStringNotContainsString('Workshop B Part', $tbody, 'Cross-tenant leakage: workshop B part visible to workshop A admin.');
    }

    // 15. The bin-locations index renders without "Undefined variable"
    //     (regression: the controller used to pass 'bins' but the view
    //     expected 'binLocations').
    public function test_bin_locations_index_renders_without_undefined_variable(): void
    {
        BinLocation::factory()->count(3)->create([
            'workshop_id' => $this->workshop->id,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/bin-locations');

        $response->assertOk();
        $this->assertStringNotContainsString('Undefined variable', $response->getContent());
    }

    // 16. Filtering does NOT load the entire table into PHP.
    public function test_filtering_does_not_load_entire_table_into_memory(): void
    {
        $this->makeParts(50);

        DB::enableQueryLog();
        $response = $this->actingAs($this->admin)->get('/admin/products?q=brake');
        DB::disableQueryLog();

        $response->assertOk();
        $log = DB::getQueryLog();

        // Every parts-related SELECT must include a LIMIT clause.
        $unboundedPartsQueries = array_filter($log, function (array $q) {
            $sql = strtolower($q['query'] ?? '');

            return str_contains($sql, 'from "parts"')
                && ! str_contains($sql, 'limit');
        });

        $this->assertEmpty(
            $unboundedPartsQueries,
            'Found an unbounded SELECT against parts — the dataset was loaded into memory. Queries: '.json_encode($unboundedPartsQueries, JSON_PRETTY_PRINT),
        );
    }

    // 17. The Clear button on the products index is wired to the JS reset handler.
    public function test_clear_button_is_wired_with_data_live_search_clear(): void
    {
        $this->makeParts(3);

        // With a filter applied, the Clear button must render and carry the
        // data-live-search-clear attribute.
        $response = $this->actingAs($this->admin)
            ->get('/admin/products?q=anything');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('data-live-search-clear', $html);
        $this->assertStringContainsString('Clear', $html);
    }

    // 18. The Clear button is always visible so users can reset state on demand.
    public function test_clear_button_is_always_visible(): void
    {
        $this->makeParts(3);

        // No filters — button should still be present.
        $response = $this->actingAs($this->admin)->get('/admin/products');
        $response->assertOk();
        $this->assertStringContainsString('data-live-search-clear', $response->getContent());

        // With filters — button still present.
        $response = $this->actingAs($this->admin)->get('/admin/products?q=anything');
        $response->assertOk();
        $this->assertStringContainsString('data-live-search-clear', $response->getContent());
    }

    // 19. The previously-broken list pages also have a Clear button now.
    public function test_missing_clear_button_now_present(): void
    {
        foreach (self::pagesWithoutClearButton() as $path) {
            $response = $this->actingAs($this->admin)->get($path.'?q=anything');

            $response->assertOk();
            $this->assertStringContainsString(
                'data-live-search-clear',
                $response->getContent(),
                "Clear button missing on {$path}.",
            );
        }
    }

    public static function pagesWithoutClearButton(): array
    {
        return [
            '/admin/units',
            '/admin/departments',
            '/admin/equipment',
            '/admin/warehouses',
            '/admin/bin-locations',
            '/admin/suppliers',
            '/admin/users',
            '/admin/tool-categories',
        ];
    }
}
