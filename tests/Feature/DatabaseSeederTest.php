<?php

declare(strict_types=1);

use BAGArt\TelegramModuleEngine\Config\TgModuleConfig;
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
    // The registry singletons are built from config during provider boot;
    // drop them so the test's config override is picked up on re-resolve.
    app()->forgetInstance(BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder::class);
    app()->forgetInstance(BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry::class);
});

test('database seeder runs registry-declared module seeders exactly once', function () {
    Config::set('tg_modules.modules', [
        'test' => new TgModuleConfig(true, TestModule::class, [DatabaseSeederProbeSeeder::class]),
    ]);
    Config::set('telegram.modules_seeders', []);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(DatabaseSeederProbeSeeder::$runs)->toBe(1);
});

test('database seeder deduplicates a module declared both in the registry and the deprecated alias', function () {
    Config::set('tg_modules.modules', [
        'test' => new TgModuleConfig(true, TestModule::class, [DatabaseSeederProbeSeeder::class]),
    ]);
    Config::set('telegram.modules_seeders', [DatabaseSeederProbeSeeder::class]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(DatabaseSeederProbeSeeder::$runs)->toBe(1);
});

test('database seeder still consumes the deprecated telegram.modules_seeders alias', function () {
    Config::set('tg_modules.modules', []);
    Config::set('telegram.modules_seeders', [DatabaseSeederProbeSeeder::class]);

    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

    expect(DatabaseSeederProbeSeeder::$runs)->toBe(1);
});
