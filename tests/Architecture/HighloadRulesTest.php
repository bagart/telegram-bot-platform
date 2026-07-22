<?php

declare(strict_types=1);

/**
 * Architecture tests — encode the load-bearing highload-stability rules so CI
 * rejects changes that violate them.
 *
 * Each test below mirrors a rule in .agents/skills/highload-stability/rules/checklist.md.
 * When a rule is added/changed there, add/update the matching test here.
 *
 * Rules that need method-body inspection (no I/O in constructors, no swallowed
 * exceptions, no dead public methods) are NOT here — Pest Arch only inspects
 * structure (interfaces/traits/inheritance/usage), not bodies. Those rules live
 * in the `tgbm:audit` command (grep-based heuristic) instead.
 */

use BAGArt\AsyncKernel\Contracts\Daemons\ASKShutdownAware;
use BAGArt\TelegramBot\Outbound\DeadLetterEntry;
use BAGArt\TelegramBot\Outbound\OutboundTask;
use BAGArt\TelegramBot\Outbound\OutboundTaskState;
use BAGArt\TelegramBot\Outbound\TgOutboundDaemon;

arch('Redis-serializable outbound DTOs must not depend on Closure — see highload-stability/redis-state-purity.md')
    ->expect([OutboundTask::class, OutboundTaskState::class, DeadLetterEntry::class])
    ->not->toUse(Closure::class);

arch('the flagship outbound daemon must implement ASKShutdownAware for graceful drain — see highload-stability/checklist.md #3')
    ->expect(TgOutboundDaemon::class)
    ->toImplement(ASKShutdownAware::class);

test('TgOutboundDaemon::shutdownPriority is in canonical range [0, 100]', function () {
    // newInstanceWithoutConstructor bypasses the 8-arg ctor; we only call pure accessors.
    $daemon = (new ReflectionClass(TgOutboundDaemon::class))->newInstanceWithoutConstructor();
    $priority = $daemon->shutdownPriority();
    expect($priority)
        ->toBeInt()
        ->toBeGreaterThanOrEqual(0)
        ->toBeLessThanOrEqual(100);
});

test('TgOutboundDaemon::shutdownTimeout is positive and sane [5, 300]', function () {
    $daemon = (new ReflectionClass(TgOutboundDaemon::class))->newInstanceWithoutConstructor();
    $timeout = $daemon->shutdownTimeout();
    expect($timeout)
        ->toBeInt()
        ->toBeGreaterThanOrEqual(5)
        ->toBeLessThanOrEqual(300);
});
