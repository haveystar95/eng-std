<?php

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // The owner's trainer rollout. Migrations ship a new trainer dark; this is which of them
        // have since been switched on, so a fresh database is the product that actually runs
        // ({@see LearningModeSettingsSeeder}). Without it a plan on a new account comes out with no
        // intro card and no speaking card and nothing says why (Д-15).
        $this->call(LearningModeSettingsSeeder::class);

        // Curated store catalogue (system/public collections + their vocab & imagery).
        $this->call(StoreContentSeeder::class);
    }
}
