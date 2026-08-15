<?php

namespace App\Notifications\Inventory;

use App\Models\Part;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to operators/admins when a part has crossed its reorder
 * threshold. Recipients are pulled from the `notifications.recipients`
 * setting (array of user IDs or role names) so this notification is
 * routing-configurable, not hard-coded.
 */
class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Part $part,
        public float $onHand,
        public int $threshold,
    ) {}

    /**
     * @return list<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Low-stock alert: {$this->part->name}")
            ->line("The part **{$this->part->name}** (SKU: {$this->part->sku}) has reached its reorder threshold.")
            ->line("On-hand quantity: {$this->onHand}")
            ->line("Reorder threshold: {$this->threshold}")
            ->action('Review part', route('admin.products.show', $this->part));
    }
}
