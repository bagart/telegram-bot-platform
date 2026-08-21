<?php

declare(strict_types=1);

namespace Modules\Example;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;

/**
 * Demo processor of the Example module. Records received message texts
 * in a public static buffer so tests can observe end-to-end delivery.
 */
class ExampleMessageProcessor implements TgModuleProcessorContract
{
    /** @var list<string> received message texts — test observation seam */
    public static array $receivedTexts = [];

    public static function moduleId(): string
    {
        return 'example';
    }

    public static function build(BotProcessorContext $context): self
    {
        return new self();
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof MessageTypeDTO && $dto->text !== null;
    }

    public function isStrictOrdered(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return false;
    }

    public function process(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
        ?TgApiTypeDTOContract $updateDto = null,
    ): void {
        assert($dto instanceof MessageTypeDTO);

        self::$receivedTexts[] = $dto->text;
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
