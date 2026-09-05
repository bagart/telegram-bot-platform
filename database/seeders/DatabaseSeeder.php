<?php

namespace Database\Seeders;

use App\Models\User;
use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
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

        // Module seed data is owned by the Telegram Module Engine registry
        // (config/tg_modules.php 'seeders'). The legacy
        // telegram.modules_seeders provider push is consumed as a
        // deprecated alias until modules migrate to the declarative form.
        $registry = $this->container->bound(EngineModuleRegistry::class)
            ? $this->container->make(EngineModuleRegistry::class)
            : null;

        $seeders = array_values(array_unique(array_merge(
            $registry?->seeders() ?? [],
            array_values(array_filter((array) config('telegram.modules_seeders', []), 'is_string')),
        )));

        foreach ($seeders as $seeder) {
            $this->call($seeder);
        }
    }
}
