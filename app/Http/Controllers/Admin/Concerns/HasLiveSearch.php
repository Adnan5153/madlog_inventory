<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Shared `search()` action for paginated list controllers.
 *
 * Returns the JSON shape consumed by the live-search JS in resources/js/app.js
 * (rows + pagination HTML + counters). Rows are pre-rendered by each
 * controller's `_row-template.blade.php` so the JS does not need to know
 * about columns.
 *
 * Pagination is performed at the database level using Laravel's native
 * `paginate($perPage)->withQueryString()`. The same per-page value used
 * by the controller's `index()` should be passed here so the JSON response
 * matches the HTML render exactly.
 */
trait HasLiveSearch
{
    /**
     * @param  \Closure(): Builder  $builder
     */
    protected function renderLiveSearch(
        Request $request,
        string $view,
        string $singular,
        \Closure $builder,
        int $perPage = 20,
    ): JsonResponse {
        $perPage = min(max(1, $perPage), 500);

        $paginator = $builder()->paginate($perPage)->withQueryString();

        $factory = app('view');
        $rowsOnPage = $paginator->getCollection();
        $shared = ['user' => $request->user()] + $this->sharedRowTemplateData($request);

        // Render the template ONCE for the whole page slice (the row template
        // loops over `$parts`), then split the rendered string into individual
        // <tr> nodes. Rendering per-row would re-iterate the entire slice
        // each time and emit N×N <tr>s.
        $sample = $rowsOnPage->isNotEmpty() ? $rowsOnPage->first() : null;
        $rendered = $factory->make($view, $this->rowTemplateData($sample, $rowsOnPage) + $shared)->render();
        $rows = $this->splitRenderedRows($rendered);

        return response()->json([
            'rows' => $rows,
            'pagination' => $paginator->hasPages()
                ? $paginator->links('vendor.pagination.bootstrap-5')->render()
                : '',
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'word' => $singular,
        ]);
    }

    /**
     * Split a rendered row-template HTML string into an array of <tr>…</tr>
     * strings. If the template produced no <tr>s (the @empty branch), the
     * caller will see an empty array and JS will show its "No results" cell.
     */
    private function splitRenderedRows(string $rendered): array
    {
        if (! str_contains($rendered, '<tr')) {
            return [];
        }

        // Capture every top-level <tr…</tr> block. The row template is
        // flat (no nested tables), so a non-greedy regex is safe and
        // matches each row independently.
        preg_match_all('#<tr\b[^>]*>.*?</tr>#s', $rendered, $matches);

        return $matches[0];
    }

    /**
     * Extra variables to expose to every row template during a live-search
     * render. Default is empty — controllers can override to expose their
     * own context (e.g. role-gated UI).
     *
     * @return array<string, mixed>
     */
    protected function sharedRowTemplateData(Request $request): array
    {
        return [];
    }

    /**
     * Variables passed to the row template view.
     *
     * By default, the partial is expected to loop over a Collection using
     * its plural noun (e.g. `@foreach ($categories as $category)`). Override
     * this method to use a different variable name.
     *
     * @return array<string, mixed>
     */
    protected function rowTemplateData(mixed $model, Collection $rows): array
    {
        $noun = $this->singularNoun();

        return $noun === '' ? [] : [Str::plural($noun) => $rows];
    }

    /**
     * The singular noun used by the row template variable. Default: none
     * (the template is expected to loop over `$rows`). Override per
     * controller when the template uses a domain-specific variable name
     * (e.g. `categories`).
     */
    protected function singularNoun(): string
    {
        return '';
    }
}
