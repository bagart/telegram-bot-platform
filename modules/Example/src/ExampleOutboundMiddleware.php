<?php

declare(strict_types=1);

namespace Modules\Example;

use BAGArt\TelegramBot\Contracts\Outbound\OutboundNextHandlerContract;
use BAGArt\TelegramBot\Outbound\OutboundEnvelope;
use BAGArt\TelegramBot\Outbound\OutboundMiddleware;
use BAGArt\TelegramBot\Outbound\OutboundSkipException;

/**
 * Demo outbound middleware: records that an envelope passed through it and
 * drops (skip) envelopes whose payload carries the marker string — proving
 * a module can influence the outbound pipeline without core edits.
 */
class ExampleOutboundMiddleware implements OutboundMiddleware
{
    public const MARKER = 'example-outbound-blocked';

    /** @var list<string> json payloads of envelopes seen by this middleware */
    public static array $seen = [];

    public function handle(
        OutboundEnvelope $envelope,
        OutboundNextHandlerContract $next,
    ): void {
        $payload = json_encode($envelope->task->jsonSerialize());
        self::$seen[] = $payload;

        if (str_contains((string)$payload, self::MARKER)) {
            throw new OutboundSkipException(reason: 'Dropped by ExampleOutboundMiddleware (demo)');
        }

        $next->handle($envelope);
    }
}
