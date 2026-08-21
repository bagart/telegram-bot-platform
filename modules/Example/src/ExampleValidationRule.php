<?php

declare(strict_types=1);

namespace Modules\Example;

use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageValidationRule;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageValidationVerdict;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageVerdictActionEnum;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;

/**
 * Demo validation rule of the Example module. Rejects messages containing
 * the trigger phrase and records matched verdicts in a public static buffer
 * so tests can observe end-to-end rule delivery.
 */
class ExampleValidationRule implements MessageValidationRule
{
    public const TRIGGER = 'example-spam-trigger';

    /** @var list<string> matched rules — test observation seam */
    public static array $matchedRules = [];

    public function priority(): int
    {
        return 30;
    }

    public function validate(MessageTypeDTO $dto): ?MessageValidationVerdict
    {
        if ($dto->text === null || !str_contains($dto->text, self::TRIGGER)) {
            return null;
        }

        self::$matchedRules[] = 'example_rule';

        return MessageValidationVerdict::reject(
            action: MessageVerdictActionEnum::Restrict,
            reason: 'example module rule match',
            matchedRule: 'example_rule',
            priority: $this->priority(),
        );
    }
}
