<?php

namespace App\Events;

use App\Models\Part;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a part's aggregated on-hand quantity falls to or below
 * its reorder threshold. Listeners (notifications) decide who to alert.
 */
class InventoryLowStockReached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Part $part,
        public float $onHand,
        public int $threshold,
    ) {}
}
