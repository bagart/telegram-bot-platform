<?php

declare(strict_types=1);

use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use BAGArt\TelegramModuleEngine\Schedule\ModuleScheduleRegistrar;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;

/**
 * Module scheduled tasks are declared in config/tg_modules.php (schedule) and
 * registered by the engine ModuleScheduleRegistrar; user overrides live in
 * config/schedule-overrides.php. Tests build a fresh Schedule against the
 * live engine registry.
 */
function scheduleModuleTasks(): Schedule
{
    $schedule = new Schedule(new DateTimeZone('UTC'));
    $registry = app(EngineModuleRegistry::class);
    (new ModuleScheduleRegistrar($schedule, $registry, (array) config('schedule-overrides', [])))->register();

    return $schedule;
}

function eventByName(Schedule $schedule, string $name): ?Event
{
    // ->name() on a scheduled event maps to its description.
    return collect($schedule->events())->first(
        fn (Event $event): bool => $event->description === $name
    );
}

describe('module schedule registrar', function () {
    it('schedules every declared module command', function () {
        $names = collect(scheduleModuleTasks()->events())
            ->map(fn (Event $event): ?string => $event->description);

        foreach (['menu:roles:sweep', 'summarizer:digests', 'stt:prune', 'tts:prune', 'mafia:sweep'] as $command) {
            expect($names)->toContain($command);
        }
    });

    it('uses the declared expressions by default', function () {
        $event = eventByName(scheduleModuleTasks(), 'menu:roles:sweep');

        expect($event)->not->toBeNull()
            ->and($event->getExpression())->toBe('0 4 * * *');
    });

    it('lets a user override the expression via config/schedule-overrides.php', function () {
        Config::set('schedule-overrides.summarizer:digests', ['expression' => '*/7 3 * * *']);

        $event = eventByName(scheduleModuleTasks(), 'summarizer:digests');

        expect($event->getExpression())->toBe('*/7 3 * * *');
    });

    it('drops tasks disabled in config/schedule-overrides.php', function () {
        Config::set('schedule-overrides.stt:prune', ['disabled' => true]);

        expect(eventByName(scheduleModuleTasks(), 'stt:prune'))->toBeNull();
    });

    it('skips entries whose enabled flag is off', function () {
        // Registry entries carry an evaluated enabled flag; flip one at the
        // config source and rebuild the registry with a fresh builder (the
        // container singleton captured the config snapshot at boot).
        Config::set('tg_modules.modules.mafia.enabled', false);
        $registry = (new BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder(
            (array) config('tg_modules'),
        ))->build()->registry;

        $schedule = new Schedule(new DateTimeZone('UTC'));
        (new ModuleScheduleRegistrar($schedule, $registry, []))->register();

        expect(eventByName($schedule, 'mafia:sweep'))->toBeNull();
    });
});
