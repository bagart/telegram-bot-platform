<?php

declare(strict_types=1);

namespace Modules\Example;

use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\Processing\Processors\TgModuleProcessorContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiTypeDTOContract;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\ErrorHandling\ProcessorErrorContext;
use BAGArt\TelegramBot\TgApi\Methods\DTO\SendMessageMethodDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;

/**
 * Demo bot command: "/example_ping" answered with "pong". Records invocations
 * in a public static buffer so tests can observe command routing end-to-end.
 */
class ExamplePingCommandProcessor implements TgModuleProcessorContract
{
    public const NAME = 'example_ping';

    /** @var list<string> chat ids the command was invoked in — test observation seam */
    public static array $invokedIn = [];

    /** @var list<string> reply texts sent — test observation seam */
    public static array $replied = [];

    private function __construct(
        private readonly TgSenderContract $sender,
    ) {
    }

    public static function moduleId(): string
    {
        return 'example';
    }

    public static function build(BotProcessorContext $context): self
    {
        return new self($context->tgSender);
    }

    public function support(
        TgApiTypeDTOContract $dto,
        TgBotConfig $botConfig,
        ?string $action = null,
    ): bool {
        return $dto instanceof MessageTypeDTO
            && $dto->text !== null
            && \BAGArt\TelegramBot\Modules\TgCommandRegistry::parseCommandName($dto->text) === self::NAME;
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
    ): void {
        assert($dto instanceof MessageTypeDTO);

        self::$invokedIn[] = $dto->chat->id;

        $replyText = 'pong';

        // Demo consumer of module settings: the reply text is configurable per
        // scope via tg_module_enablements.module_settings ('ping_reply' key).
        // Only available inside the Laravel app — the pure-PHP path keeps the default.
        if ($botConfig->botId !== null && function_exists('app')) {
            try {
                $settings = app(\BAGArt\TelegramBot\Contracts\Modules\ModuleSettingsContract::class)
                    ->settingsFor('example', $botConfig->botId, (int)$dto->chat->id);
                $replyText = (string)($settings['ping_reply'] ?? $replyText);
            } catch (\Throwable) {
                // settings are optional — the default reply stays
            }
        }

        self::$replied[] = $replyText;

        $this->sender->send(
            $botConfig,
            new SendMessageMethodDTO(
                chatId: $dto->chat->id,
                text: $replyText,
            ),
        );
    }

    public function onException(ProcessorErrorContext $context): void
    {
    }
}
