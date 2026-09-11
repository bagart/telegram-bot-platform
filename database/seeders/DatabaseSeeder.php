<?php

namespace Database\Seeders;

use App\Models\User;
use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Module seed data is owned by the Telegram Module Engine registry
        // (config/tg_modules.php 'seeders').
        $seeders = $this->container->bound(EngineModuleRegistry::class)
            ? $this->container->make(EngineModuleRegistry::class)->seeders()
            : [];

        foreach ($seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
