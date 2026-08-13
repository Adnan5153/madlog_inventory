<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * Append-only audit trail for important user actions. Workshop-scoped so
 * admins of one workshop can review their own history without leaking
 * across workshops. Global admins see everything via WorkshopScope.
 *
 * @property int $id
 * @property int|null $workshop_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $changes
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
#[Fillable([
    'workshop_id',
    'user_id',
    'action',
    'subject_type',
    'subject_id',
    'changes',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * Boot: append-only. Updates and deletes throw.
     */
    public static function boot(): void
    {
        parent::boot();

        static::updating(function (): bool {
            throw new \LogicException('AuditLog rows are append-only.');
        });

        static::deleting(function (): bool {
            throw new \LogicException('AuditLog rows cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * Polymorphic subject (e.g. Part, PurchaseOrder, User).
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Convenience helper for the common case of recording an action.
     * Caller must already be authorized.
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $changes = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        $user = auth()->user();

        return static::create([
            'workshop_id' => $user?->workshop_id,
            'user_id' => $user?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null ? Str::limit($userAgent, 512, '') : null,
        ]);
    }
}