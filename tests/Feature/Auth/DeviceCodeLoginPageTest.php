<?php

declare(strict_types=1);

use BAGArt\TelegramBotMenu\Models\TgLoginChallenge;
use Inertia\Testing\AssertableInertia as Assert;

test('the default login screen mints a device-code challenge', function () {
    $response = $this->get(route('login'));

    $response->assertOk()->assertInertia(fn (Assert $page): Assert => $page
        ->component('auth/login')
        ->has('challenge.id')
        ->has('challenge.code')
        ->where('challenge.botUsername', config('menu.login_bot_username'))
        ->has('challenge.expiresAt'));

    $challengeId = $response->inertiaProps()['challenge']['id'];
    $challenge = TgLoginChallenge::query()->findOrFail($challengeId);

    expect($challenge->status->value)->toBe('pending')
        ->and($challenge->purpose->value)->toBe('login');
});

test('the break-glass email form stays reachable at /login/email', function () {
    $this->get('/login/email')
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->component('auth/email-login'));
});
