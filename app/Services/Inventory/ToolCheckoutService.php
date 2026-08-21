<?php

namespace App\Services\Inventory;

use App\Enums\ToolCheckoutStatus;
use App\Enums\ToolCondition;
use App\Enums\ToolStatus;
use App\Exceptions\DomainException;
use App\Models\AuditLog;
use App\Models\Tool;
use App\Models\ToolCheckout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Writes the canonical `tool_checkouts` lifecycle events. Every change
 * to a tool's `status` and `current_holder_user_id` must flow through
 * this service so the checkout ledger, the tool row, and the audit log
 * stay consistent.
 *
 * App-level invariant: there is at most ONE open checkout per tool at a
 * time. Both methods are wrapped in DB::transaction() so a partial
 * failure does not leave the tool in a settled but inconsistent state.
 */
class ToolCheckoutService
{
    /**
     * Issue a tool to a user.
     *
     * @throws DomainException when the tool is not currently available or
     *                         already has an open checkout.
     */
    public function checkout(
        Tool $tool,
        User $user,
        User $issuedBy,
        ?Carbon $expectedReturn = null,
        ?string $purpose = null,
        ?string $notes = null,
    ): ToolCheckout {
        return DB::transaction(function () use ($tool, $user, $issuedBy, $expectedReturn, $purpose, $notes) {
            $tool->refresh();

            if (! $tool->status->isCheckoutable()) {
                throw new DomainException(sprintf(
                    'Tool "%s" cannot be checked out from status "%s".',
                    $tool->name,
                    $tool->status->label(),
                ));
            }

            if ($tool->checkouts()->whereNull('returned_at')->exists()) {
                throw new DomainException(sprintf(
                    'Tool "%s" already has an open checkout.',
                    $tool->name,
                ));
            }

            $checkout = ToolCheckout::create([
                'workshop_id' => $tool->workshop_id,
                'tool_id' => $tool->id,
                'user_id' => $user->id,
                'issued_by' => $issuedBy->id,
                'checked_out_at' => now(),
                'expected_return_at' => $expectedReturn,
                'returned_at' => null,
                'received_by' => null,
                'purpose' => $purpose,
                'notes' => $notes,
                'condition_at_return' => null,
                'status' => ToolCheckoutStatus::Open->value,
            ]);

            $tool->status = ToolStatus::CheckedOut;
            $tool->current_holder_user_id = $user->id;
            $tool->save();

            AuditLog::record('tool.checked_out', $tool, [
                'checkout_id' => $checkout->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'issued_by' => $issuedBy->id,
                'expected_return_at' => $expectedReturn?->toIso8601String(),
                'purpose' => $purpose,
            ]);

            return $checkout;
        });
    }

    /**
     * Return a tool that is currently checked out.
     *
     * @throws DomainException when the tool has no open checkout.
     */
    public function checkin(
        Tool $tool,
        User $receivedBy,
        ToolCondition $conditionAtReturn,
        ?string $notes = null,
    ): ToolCheckout {
        return DB::transaction(function () use ($tool, $receivedBy, $conditionAtReturn, $notes) {
            $tool->refresh();

            $checkout = $tool->checkouts()->whereNull('returned_at')->orderByDesc('checked_out_at')->first();

            if ($checkout === null) {
                throw new DomainException(sprintf(
                    'Tool "%s" has no open checkout to check in.',
                    $tool->name,
                ));
            }

            $checkout->returned_at = now();
            $checkout->received_by = $receivedBy->id;
            $checkout->condition_at_return = $conditionAtReturn;
            if ($notes !== null) {
                $checkout->notes = $notes;
            }
            $checkout->status = ToolCheckoutStatus::Closed->value;
            $checkout->save();

            $tool->status = ToolStatus::Available;
            $tool->current_holder_user_id = null;
            $tool->condition = $conditionAtReturn;
            $tool->save();

            AuditLog::record('tool.checked_in', $tool, [
                'checkout_id' => $checkout->id,
                'received_by' => $receivedBy->id,
                'condition_at_return' => $conditionAtReturn->value,
                'notes' => $notes,
            ]);

            return $checkout;
        });
    }
}
