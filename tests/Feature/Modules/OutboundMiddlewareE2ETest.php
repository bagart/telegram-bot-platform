<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Outbound\OutboundEnvelope;
use BAGArt\TelegramBot\Outbound\OutboundMiddlewareRegistry;
use BAGArt\TelegramBot\Outbound\OutboundSkipException;
use BAGArt\TelegramBot\Outbound\OutboundTask;
use BAGArt\TelegramBot\Outbound\OutboundTaskState;
use BAGArt\TelegramBot\TgBotSetupFactory;
use Modules\Example\ExampleOutboundMiddleware;

beforeEach(function () {
    config('telegram.modules'); // forces the module config scan
    ExampleOutboundMiddleware::$seen = [];
});

it('Example module middleware lands in the shared OutboundMiddlewareRegistry singleton', function () {
    expect(app(OutboundMiddlewareRegistry::class)->classes())
        ->toContain(ExampleOutboundMiddleware::class);
});

it('module middleware is wired into the outbound daemon pipeline before the executor (e2e)', function () {
    $parts = app(TgBotSetupFactory::class)->createOutboundDaemonParts();

    $marker = ExampleOutboundMiddleware::MARKER;

    $envelope = new OutboundEnvelope(
        task: new OutboundTask(
            id: 'e2e-task',
            botConfig: new TgBotConfig(token: 't:token', botId: 'test_bot'),
            dtoClass: 'SomeDto',
            dtoData: ['text' => "drop me {$marker}"],
        ),
        state: new OutboundTaskState(),
    );

    try {
        $parts['pipeline']->execute($envelope);
        $this->fail('OutboundSkipException was expected — the module middleware did not run');
    } catch (OutboundSkipException $e) {
        // dropped before the executor: no network call happened
    }

    expect(ExampleOutboundMiddleware::$seen)->toHaveCount(1);
});
