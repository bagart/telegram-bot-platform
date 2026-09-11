<?php

declare(strict_types=1);

use BAGArt\TelegramModuleEngine\Config\TgModuleConfig;
use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder;
use BAGArt\TelegramModuleEngine\Tests\Fixtures\TestModule;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

/**
 * Probe seeder recording invocations. Defined in-file because it exists only
 * to prove the engine-driven DatabaseSeeder executes each seeder exactly once.
 */
final class DatabaseSeederProbeSeeder extends Illuminate\Database\Seeder
{
    public static int $runs = 0;

    public function run(): void
    {
        self::$runs++;
    }
}

beforeEach(function () {
    DatabaseSeederProbeSeeder::$runs = 0;
    app()->forgetInstance(ModuleRegistryBuilder::class);
    app()->forgetInstance(EngineModuleRegistry::class);
});

test('database seeder runs registry-declared module seeders exactly once', function () {
    Config::set('tg_modules.modules', [
        'test' => new TgModuleConfig(true, TestModule::class, seeders: [DatabaseSeederProbeSeeder::class]),
    ]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(DatabaseSeederProbeSeeder::$runs)->toBe(1);
});

test('database seeder runs multiple registry seeders', function () {
    Config::set('tg_modules.modules', [
        'test-a' => new TgModuleConfig(true, TestModule::class, seeders: [DatabaseSeederProbeSeeder::class]),
        'test-b' => new TgModuleConfig(true, TestModule::class, seeders: [DatabaseSeederProbeSeeder::class]),
    ]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(DatabaseSeederProbeSeeder::$runs)->toBe(2);
});

test('database seeder succeeds with no seeders registered', function () {
    Config::set('tg_modules.modules', []);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertExitCode(0);
});
