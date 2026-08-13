<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workshop_id' => Workshop::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'user.role_changed',
                'user.invited',
                'part.created',
                'part.updated',
                'part.deleted',
                'inventory.adjusted',
                'purchase_order.approved',
                'purchase_order.received',
                'tool.checked_out',
                'tool.returned',
                'job_card.opened',
                'job_card.closed',
            ]),
            'subject_type' => null,
            'subject_id' => null,
            'changes' => [
                'before' => ['foo' => 'bar'],
                'after' => ['foo' => 'baz'],
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
