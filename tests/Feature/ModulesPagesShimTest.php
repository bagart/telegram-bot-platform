<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

/**
 * Host shim for module-owned frontend tooling: `modules:pages` iterates the
 * telegram.modules_page_generators registry (self-registered by module
 * service providers), so package.json / CI never name a module command.
 */
test('modules:pages delegates to every registered generator command', function () {
    Config::set('telegram.modules_page_generators', ['fake:gen-a', 'fake:gen-b']);

    Artisan::command('fake:gen-a {--output= : path}', function (): int {
        config(['test.gen-a-called' => true]);

        return 0;
    });
    Artisan::command('fake:gen-b {--output= : path}', function (): int {
        config(['test.gen-b-called' => true]);

        return 0;
    });

    $this->artisan('modules:pages')->assertExitCode(0);

    expect(config('test.gen-a-called'))->toBeTrue()
        ->and(config('test.gen-b-called'))->toBeTrue();
});

test('modules:pages succeeds without registered generators', function () {
    Config::set('telegram.modules_page_generators', []);

    $this->artisan('modules:pages')->assertExitCode(0);
});

test('menu module registers its generator into the registry on boot', function () {
    // Refresh the in-memory registry to what a full boot would produce.
    $registered = in_array('menu:pages', array_map(strval(...), (array) config('telegram.modules_page_generators')), true);

    // In the booted test app the provider has already merged its entry.
    expect($registered)->toBeTrue();
});
