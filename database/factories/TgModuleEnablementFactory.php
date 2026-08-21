<?php

declare(strict_types=1);

namespace Database\Factories;

use BAGArt\TelegramBotManagement\Models\TgModuleEnablement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TgModuleEnablement> */
class TgModuleEnablementFactory extends Factory
{
    protected $model = TgModuleEnablement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'bot_id' => null,
            'chat_id' => null,
            'module_id' => 'example',
            'is_enabled' => true,
            'module_settings' => null,
        ];
    }

    public function forChat(string $botId, int $chatId): static
    {
        return $this->state(fn (): array => [
            'bot_id' => $botId,
            'chat_id' => $chatId,
        ]);
    }

    public function forBot(string $botId): static
    {
        return $this->state(fn (): array => [
            'bot_id' => $botId,
            'chat_id' => null,
        ]);
    }

    public function platform(): static
    {
        return $this->state(fn (): array => [
            'bot_id' => null,
            'chat_id' => null,
        ]);
    }

    public function enabled(bool $enabled): static
    {
        return $this->state(fn (): array => ['is_enabled' => $enabled]);
    }
}
