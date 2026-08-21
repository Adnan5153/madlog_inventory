<?php

namespace App\Models;

use App\Enums\ToolMaintenanceType;
use Database\Factories\ToolMaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single maintenance event on a tool. History is append-only at the
 * application level — records are not deleted on tool update.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $tool_id
 * @property string $type
 * @property int|null $performed_by
 * @property string|null $vendor
 * @property numeric|null $cost
 * @property Carbon $performed_at
 * @property Carbon|null $next_due_at
 * @property string|null $description
 */
#[Fillable([
    'workshop_id',
    'tool_id',
    'type',
    'performed_by',
    'vendor',
    'cost',
    'performed_at',
    'next_due_at',
    'description',
])]
class ToolMaintenanceRecord extends Model
{
    use Concerns\BelongsToWorkshop;
    /** @use HasFactory<ToolMaintenanceRecordFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ToolMaintenanceType::class,
            'cost' => 'decimal:2',
            'performed_at' => 'datetime',
            'next_due_at' => 'datetime',
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function isOverdue(): bool
    {
        return $this->next_due_at !== null && $this->next_due_at->isPast();
    }
}
