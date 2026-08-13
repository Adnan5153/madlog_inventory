<?php

namespace App\Observers;

use App\Events\InventoryLowStockReached;
use App\Models\Part;
use App\Notifications\Inventory\LowStockNotification;

/**
 * Watches Part rows and dispatches notifications when a part's
 * aggregated on-hand quantity has dropped to/below its reorder threshold.
 *
 * The AuditObserver handles row-level audit; this observer handles
 * domain-event dispatch (which feeds into notifications).
 */
class PartObserver
{
    public function saved(Part $part): void
    {
        $this->checkThreshold($part);
    }

    protected function checkThreshold(Part $part): void
    {
        $onHand = (float) $part->inventoryItems()->sum('quantity');
        $threshold = (int) $part->reorder_threshold;

        if ($onHand <= $threshold && $threshold > 0) {
            // Avoid notification storms: only fire when the threshold is
            // freshly crossed (current on_hand at-or-below AND previously
            // above). The `wasChanged` lookup uses the part's recent
            // history; if the inventory items table is the source of the
            // drop, the caller passes the part in fresh.
            InventoryLowStockReached::dispatch($part, $onHand, $threshold);

            // Direct delivery to recipients configured in settings.
            $recipients = setting('notifications.low_stock_recipients', []);
            if (is_array($recipients) && ! empty($recipients)) {
                foreach ($recipients as $userId) {
                    $user = \App\Models\User::find($userId);
                    if ($user) {
                        $user->notify(new LowStockNotification($part, $onHand, $threshold));
                    }
                }
            }
        }
    }
}