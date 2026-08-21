<?php

namespace App\Models;

use App\Enums\ToolCheckoutStatus;
use App\Enums\ToolCondition;
use Database\Factories\ToolCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single checkout / check-in event for a tool. The "open" checkout
 * (returned_at IS NULL) is the tool's current holder. Multiple rows
 * accumulate over time as history. Only one open checkout per tool is
 * allowed at any moment — enforced by ToolCheckoutService.
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $tool_id
 * @property int $user_id
 * @property int|null $issued_by
 * @property Carbon $checked_out_at
 * @property Carbon|null $expected_return_at
 * @property Carbon|null $returned_at
 * @property int|null $received_by
 * @property string|null $purpose
 * @property string|null $notes
 * @property string|null $condition_at_return
 * @property string $status
 */
#[Fillable([
    'workshop_id',
    'tool_id',
    'user_id',
    'issued_by',
    'checked_out_at',
    'expected_return_at',
    'returned_at',
    'received_by',
    'purpose',
    'notes',
    'condition_at_return',
    'status',
])]
class ToolCheckout extends Model
{
    use Concerns\BelongsToWorkshop;

    /** @use HasFactory<ToolCheckoutFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'returned_at' => 'datetime',
            'condition_at_return' => ToolCondition::class,
            'status' => ToolCheckoutStatus::class,
        ];
    }

    public function tool(): BelongsTo
    {
        return $this->belongsTo(Tool::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }

    public function isClosed(): bool
    {
        return $this->returned_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->expected_return_at !== null
            && $this->expected_return_at->isPast();
    }
}
