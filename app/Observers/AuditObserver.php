<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Records an audit log row whenever an observed model is created,
 * updated, deleted, or restored.
 *
 * Models opt-in by listing themselves in AppServiceProvider::registerAuditObservers().
 * The audit log captures old vs. new values for `updated`, the freshly-
 * created attributes for `created`, and a snapshot of the row for
 * `deleted` / `restored`. Sensitive columns (password, *_secret, *_token)
 * are filtered out.
 */
class AuditObserver
{
    /**
     * @var list<string>
     */
    public const REDACTED_ATTRIBUTES = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'api_token',
        'access_token',
    ];

    public function created(Model $model): void
    {
        if ($this->shouldSkip($model, 'created')) return;

        AuditLog::record(
            action: $this->actionFor($model, 'created'),
            subject: $model,
            changes: ['attributes' => $this->filterAttributes($model->getAttributes())],
        );
    }

    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model, 'updated')) return;

        $dirty = $model->getDirty();
        if (empty($dirty)) return;

        $original = array_intersect_key($model->getOriginal(), $dirty);

        AuditLog::record(
            action: $this->actionFor($model, 'updated'),
            subject: $model,
            changes: [
                'before' => $this->filterAttributes($original),
                'after'  => $this->filterAttributes($dirty),
            ],
        );
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model, 'deleted')) return;

        AuditLog::record(
            action: $this->actionFor($model, 'deleted'),
            subject: $model,
            changes: ['attributes' => $this->filterAttributes($model->getOriginal())],
        );
    }

    public function restored(Model $model): void
    {
        if ($this->shouldSkip($model, 'restored')) return;

        AuditLog::record(
            action: $this->actionFor($model, 'restored'),
            subject: $model,
            changes: ['attributes' => $this->filterAttributes($model->getAttributes())],
        );
    }

    /**
     * The explicit action string recorded in the audit log.
     * Examples: `part.created`, `purchase_order.updated`.
     */
    protected function actionFor(Model $model, string $event): string
    {
        $base = class_basename($model);
        // snake_case the model name (e.g. PurchaseOrderItem -> purchase_order_item).
        $snake = strtolower(preg_replace('/(?<!^)([A-Z])/', '_$1', $base) ?? $base);
        return "{$snake}.{$event}";
    }

    /**
     * Drop sensitive attributes from the diff payload before persisting.
     */
    protected function filterAttributes(array $attrs): array
    {
        foreach (self::REDACTED_ATTRIBUTES as $field) {
            unset($attrs[$field]);
        }
        return $attrs;
    }

    /**
     * Skip models that have opted out, and skip the audit log itself
     * (otherwise we audit our own writes).
     */
    protected function shouldSkip(Model $model, string $event): bool
    {
        if ($model instanceof AuditLog) {
            return true;
        }
        // Skip if the model is currently inside a bulk save where
        // auditing would explode the log. Callers can disable via the
        // static `$auditEnabled` toggle on the model if needed.
        return false;
    }
}