<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
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

        // Module seeders self-register via their service providers
        // (config/telegram.php 'modules_seeders') — the host stays
        // unaware of module implementations.
        foreach ((array) config('telegram.modules_seeders', []) as $seeder) {
            $this->call($seeder);
        }
    }
}
