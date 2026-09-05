<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Module self-registration contract: the menu module owns its Mini App
 * throttle buckets (menu RFC §27.8) — the host AppServiceProvider must stay
 * free of module-specific limiter wiring. These tests pin the registered
 * buckets and their config-driven limits; they fail when the module stops
 * self-registering or hardcodes limits against the config tree.
 */
test('menu module self-registers tgapp throttle buckets', function () {
    foreach (['tgapp-session', 'tgapp-read', 'tgapp-write', 'tgapp-api'] as $name) {
        expect(RateLimiter::limiter($name))->toBeCallable();
    }
});

test('session bucket key hashes initData and keys by IP', function () {
    $request = Request::create(
        '/tgapp/api/v1/session',
        'POST',
        ['initData' => 'query-id-1'],
        [],
        [],
        ['REMOTE_ADDR' => '10.0.0.7'],
    );

    /** @var Limit $limit */
    $limit = RateLimiter::limiter('tgapp-session')($request);

    expect($limit->key)->toContain('sess|')
        ->and($limit->key)->toContain('|'.substr(hash('sha256', 'query-id-1'), 0, 16))
        ->and($limit->key)->toContain('10.0.0.7');
});

test('authenticated api buckets key by tg uid with anonymous IP fallback', function () {
    $limiter = RateLimiter::limiter('tgapp-write');

    $anon = Request::create('/x', 'PUT');
    $anon->attributes->set('tgapp.user_id', null);

    $authed = Request::create('/x', 'PUT');
    $authed->attributes->set('tgapp.user_id', 42);

    /** @var Limit $anonLimit */
    $anonLimit = $limiter($anon);
    /** @var Limit $authedLimit */
    $authedLimit = $limiter($authed);

    expect($anonLimit->key)->toContain('|anon|')
        ->and($authedLimit->key)->toContain('|42');
});

test('bucket limits come from the menu config tree, not hardcoded values', function () {
    config(['menu.throttle.read' => 77]);

    $request = Request::create('/x', 'GET');
    $request->attributes->set('tgapp.user_id', 1);

    /** @var Limit $limit */
    $limit = RateLimiter::limiter('tgapp-read')($request);

    expect($limit->maxAttempts)->toBe(77);
});
