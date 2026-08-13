<?php

namespace Database\Factories;

use App\Models\JobCard;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobCard>
 */
class JobCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_number' => 'JC-'.date('Y').'-'.Str::upper(Str::random(5)),
            'workshop_id' => Workshop::factory(),
            'mechanic_id' => User::factory(),
            'created_by' => User::factory(),
            'vehicle_make' => fake()->randomElement(['Toyota', 'Honda', 'Ford', 'BMW', 'Mercedes', 'Hyundai', 'Maruti']),
            'vehicle_model' => fake()->word(),
            'vehicle_plate' => strtoupper(fake()->bothify('??-##-####')),
            'vehicle_vin' => strtoupper(fake()->bothify('?##############?')),
            'status' => fake()->randomElement(['open', 'in_progress', 'completed', 'cancelled']),
            'description' => fake()->paragraph(),
            'opened_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
            'closed_at' => null,
        ];
    }
}
