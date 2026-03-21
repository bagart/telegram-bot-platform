<?php

declare(strict_types=1);

use BAGArt\ASKClient\Lockers\InMemoryLocker;
use BAGArt\AsyncKernel\ASKClock;
use BAGArt\AsyncKernel\Drivers\ASKFiberScheduler;
use BAGArt\AsyncKernel\Wrappers\ASKCacheWrapper;
use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Outbound\Adapters\InMemoryOutboundQueue;
use BAGArt\TelegramBot\Outbound\Adapters\KernelCacheAdapter;
use BAGArt\TelegramBot\Outbound\Config\OutboundSetup;
use BAGArt\TelegramBot\Outbound\Config\OutboundWorkerConfig;
use BAGArt\TelegramBot\Outbound\LeaseRenewer;
use BAGArt\TelegramBot\Outbound\OutboundEnvelope;
use BAGArt\TelegramBot\Outbound\OutboundPipeline;
use BAGArt\TelegramBot\Outbound\OutboundTask;
use BAGArt\TelegramBot\Outbound\OutboundTaskState;
use BAGArt\TelegramBot\Outbound\OutboundCircuitBreaker;
use BAGArt\TelegramBot\Outbound\TgOutboundDaemon;
use BAGArt\TelegramBot\Outbound\TgOutboundStats;
use Psr\SimpleCache\CacheInterface;

if (!function_exists('makeTestOutboundCache')) {
    function makeTestOutboundCache(): KernelCacheAdapter
    {
        return new KernelCacheAdapter(
            new ASKCacheWrapper(new class () implements CacheInterface {
                private array $store = [];

                public function get(string $key, mixed $default = null): mixed
                {
                    return $this->store[$key] ?? $default;
                }

                public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
                {
                    $this->store[$key] = $value;
                    return true;
                }

                public function delete(string $key): bool
                {
                    unset($this->store[$key]);
                    return true;
                }

                public function clear(): bool
                {
                    $this->store = [];
                    return true;
                }

                public function getMultiple(iterable $keys, mixed $default = null): iterable { return []; }
                public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool { return true; }
                public function deleteMultiple(iterable $keys): bool { return true; }
                public function has(string $key): bool { return isset($this->store[$key]); }
            }),
            new InMemoryLocker(),
        );
    }
}

beforeEach(function () {
    $clock = new ASKClock();
    $queue = new InMemoryOutboundQueue($clock);
    $cache = makeTestOutboundCache();
    $stats = new TgOutboundStats($cache);
    $pipeline = new OutboundPipeline([]);
    $leaseRenewer = new LeaseRenewer($queue, $clock);
    $logger = new ASKLogWrapper();
    $config = new OutboundWorkerConfig();
    $scheduler = new ASKFiberScheduler();
    $cb = new OutboundCircuitBreaker($cache);

    $worker = new TgOutboundDaemon(
        queue: $queue,
        pipeline: $pipeline,
        circuitBreaker: $cb,
        stats: $stats,
        leaseRenewer: $leaseRenewer,
        logger: $logger,
        config: $config,
        scheduler: $scheduler,
    );

    $sender = Mockery::mock(TgSenderContract::class);
    $this->outboundSetup = new OutboundSetup($worker, $stats, $queue, $sender, $pipeline, $cb, $leaseRenewer, $scheduler);
    $this->app->instance(OutboundSetup::class, $this->outboundSetup);
});

describe('TgOutboundMetricsCommand', function () {
    it('shows metrics with JSON output', function () {
        $this->artisan('tg:outbound:metrics', ['--json' => true])
            ->assertOk();
    });

    it('shows metrics with from/to hours', function () {
        $from = date('YmdH', time() - 7200);
        $to = date('YmdH');

        $this->artisan('tg:outbound:metrics', [
            '--from' => $from,
            '--to' => $to,
            '--json' => true,
        ])->assertOk();
    });

    it('shows metrics for a specific bot', function () {
        $this->artisan('tg:outbound:metrics', [
            '--bot-id' => 'bot1',
            '--json' => true,
        ])->assertOk();
    });
});
