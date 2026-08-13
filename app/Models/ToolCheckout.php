<?php

namespace App\Models;

use Database\Factories\ToolCheckoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single checkout event for a tool. Multiple rows may exist per tool
 * over time, but only one may be "open" (returned_at IS NULL) at any
 * given moment. Enforced in application code (see ToolCheckoutService).
 *
 * @property int $id
 * @property int $workshop_id
 * @property int $tool_id
 * @property int $user_id
 * @property int|null $issued_by
 * @property \Illuminate\Support\Carbon $checked_out_at
 * @property \Illuminate\Support\Carbon|null $returned_at
 * @property \Illuminate\Support\Carbon|null $expected_return_at
 * @property string|null $notes
 */
#[Fillable([
    'workshop_id',
    'tool_id',
    'user_id',
    'issued_by',
    'checked_out_at',
    'returned_at',
    'expected_return_at',
    'notes',
])]
class ToolCheckout extends Model
{
    /** @use HasFactory<ToolCheckoutFactory> */
    use HasFactory;
    use Concerns\BelongsToWorkshop;

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
            'expected_return_at' => 'datetime',
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

    public function isOpen(): bool
    {
        return $this->returned_at === null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen()
            && $this->expected_return_at !== null
            && $this->expected_return_at->isPast();
    }
}