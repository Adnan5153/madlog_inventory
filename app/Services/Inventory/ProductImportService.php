<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Unit;
use App\Models\User;
use App\Scopes\WorkshopScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Streams a CSV of parts and creates one Part row per record.
 *
 * The CSV layout is documented in /admin/products (download link).
 * Columns: sku, name, oem_part_number, barcode, description, category,
 * brand, unit, cost_price, sale_price, reorder_threshold,
 * reorder_quantity, is_active.
 *
 * Category / brand / unit names are looked up by exact case-insensitive
 * match against the workshop-scoped master-data rows; a new category is
 * auto-created with a slug if the operator didn't ship it first.
 */
class ProductImportService
{
    public const REQUIRED_HEADERS = ['sku', 'name', 'cost_price', 'sale_price'];

    public const OPTIONAL_HEADERS = [
        'oem_part_number', 'barcode', 'description',
        'category', 'brand', 'unit',
        'reorder_threshold', 'reorder_quantity', 'is_active',
    ];

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function importCsv(UploadedFile $file, User $actor, int $workshopId): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $path = $file->getRealPath();
        if (! $path) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Could not read uploaded file.']];
        }

        $handle = fopen($path, 'rb');
        if (! $handle) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ['Could not open uploaded file.']];
        }

        $headers = null;
        $rowNum = 0;
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if ($headers === null) {
                    $headers = array_map(fn ($h) => strtolower(trim((string) $h)), $row);
                    $missing = array_diff(self::REQUIRED_HEADERS, $headers);
                    if ($missing) {
                        $errors[] = "Missing required columns: " . implode(', ', $missing);
                        break;
                    }
                    continue;
                }
                if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue; // skip blank lines
                }

                $data = array_combine($headers, array_pad($row, count($headers), null));

                try {
                    $result = $this->upsertRow($data, $workshopId);
                    if ($result === 'created') $created++;
                    elseif ($result === 'updated') $updated++;
                    else $skipped++;
                } catch (Throwable $e) {
                    $errors[] = "Row {$rowNum}: " . $e->getMessage();
                }
            }
        } finally {
            fclose($handle);
        }

        AuditLog::record('parts.imported', null, [
            'workshop_id' => $workshopId,
            'file'        => $file->getClientOriginalName(),
            'created'     => $created,
            'updated'     => $updated,
            'skipped'     => $skipped,
            'errors'      => count($errors),
            'actor_id'    => $actor->id,
        ]);

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * Build the CSV string used by the export endpoint.
     */
    public function exportCsv(int $workshopId): string
    {
        $rows = WorkshopScope::disabled(function () use ($workshopId) {
            return Part::query()
                ->where('workshop_id', $workshopId)
                ->with(['category:id,name', 'brand:id,name', 'unit:id,short_code'])
                ->orderBy('name')
                ->get();
        });

        $buf = fopen('php://temp', 'r+');
        $headers = ['sku', 'name', 'oem_part_number', 'barcode', 'description',
            'category', 'brand', 'unit', 'cost_price', 'sale_price',
            'reorder_threshold', 'reorder_quantity', 'is_active'];
        fputcsv($buf, $headers);

        foreach ($rows as $p) {
            fputcsv($buf, [
                $p->sku,
                $p->name,
                $p->oem_part_number,
                $p->barcode,
                $p->description,
                $p->category?->name,
                $p->brand?->name,
                $p->unit?->short_code,
                $p->cost_price,
                $p->sale_price,
                $p->reorder_threshold,
                $p->reorder_quantity,
                $p->is_active ? 1 : 0,
            ]);
        }

        rewind($buf);
        return stream_get_contents($buf) ?: '';
    }

    protected function upsertRow(array $data, int $workshopId): string
    {
        $sku = trim((string) ($data['sku'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('Name is required.');
        }

        $existing = null;
        if ($sku !== '') {
            $existing = WorkshopScope::disabled(function () use ($workshopId, $sku) {
                return Part::query()
                    ->where('workshop_id', $workshopId)
                    ->where('sku', $sku)
                    ->first();
            });
        }

        $payload = [
            'workshop_id'       => $workshopId,
            'name'              => $name,
            'sku'               => $sku ?: null,
            'oem_part_number'   => $this->nullableString($data, 'oem_part_number'),
            'barcode'           => $this->nullableString($data, 'barcode'),
            'description'       => $this->nullableString($data, 'description'),
            'category_id'       => $this->resolveCategoryId($data['category'] ?? null, $workshopId),
            'brand_id'          => $this->resolveBrandId($data['brand'] ?? null, $workshopId),
            'unit_id'           => $this->resolveUnitId($data['unit'] ?? null),
            'cost_price'        => (float) ($data['cost_price'] ?? 0),
            'sale_price'        => (float) ($data['sale_price'] ?? 0),
            'reorder_threshold' => (int) ($data['reorder_threshold'] ?? 0),
            'reorder_quantity'  => (int) ($data['reorder_quantity'] ?? 0),
            'is_active'         => ! in_array(strtolower((string) ($data['is_active'] ?? '1')), ['0', 'false', 'no'], true),
        ];

        if ($existing) {
            $existing->update($payload);
            return 'updated';
        }

        Part::create($payload);
        return 'created';
    }

    protected function nullableString(array $data, string $key): ?string
    {
        $v = $data[$key] ?? null;
        return ($v === null || $v === '') ? null : (string) $v;
    }

    protected function resolveCategoryId(mixed $value, int $workshopId): ?int
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $cat = WorkshopScope::disabled(function () use ($workshopId, $value) {
            return PartCategory::query()
                ->where('workshop_id', $workshopId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($value)])
                ->first();
        });
        if ($cat) return $cat->id;

        $cat = PartCategory::create([
            'workshop_id' => $workshopId,
            'name'        => $value,
            'slug'        => Str::slug($value),
        ]);
        return $cat->id;
    }

    protected function resolveBrandId(mixed $value, int $workshopId): ?int
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $brand = WorkshopScope::disabled(function () use ($workshopId, $value) {
            return Brand::query()
                ->where('workshop_id', $workshopId)
                ->whereRaw('LOWER(name) = ?', [Str::lower($value)])
                ->first();
        });
        if ($brand) return $brand->id;

        $brand = Brand::create([
            'workshop_id' => $workshopId,
            'name'        => $value,
            'slug'        => Str::slug($value),
        ]);
        return $brand->id;
    }

    protected function resolveUnitId(mixed $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $unit = Unit::query()
            ->whereRaw('LOWER(short_code) = ?', [Str::lower($value)])
            ->orWhereRaw('LOWER(name) = ?', [Str::lower($value)])
            ->first();
        return $unit?->id;
    }
}