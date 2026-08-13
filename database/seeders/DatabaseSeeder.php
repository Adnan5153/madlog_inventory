<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * - Outside production: delegates to DevelopmentSeeder so a freshly
     *   `migrate:fresh --seed`’d database is usable for local exploration.
     * - In production or when `$this->command` is unavailable: this is
     *   a no-op. Run seeders explicitly in production via
     *   `php artisan db:seed --class=YourSeeder`.
     */
    public function run(): void
    {
        // RBAC and runtime settings ship with every install.
        $this->call([
            RolesAndPermissionsSeeder::class,
            SettingsSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DevelopmentSeeder::class);

            return;
        }

        // In production, fall back to the original minimal behavior so
        // `php artisan db:seed` still creates at least one verified user.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
