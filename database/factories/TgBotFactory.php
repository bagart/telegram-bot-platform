<?php

declare(strict_types=1);

namespace Database\Factories;

use BAGArt\TelegramBotManagement\Enums\BotAvailability;
use BAGArt\TelegramBotManagement\Enums\BotClass;
use BAGArt\TelegramBotManagement\Models\TgBot;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TgBot> */
class TgBotFactory extends Factory
{
    protected $model = TgBot::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $botId = fake()->unique()->numberBetween(100_000_000, 9_999_999_999);

        return [
            'bot_id' => (string) $botId,
            'token' => $botId.':'.str_repeat('a', 35),
            'secret_token' => null,
            'class' => BotClass::Platform,
            'availability' => BotAvailability::Public,
            'external_token' => null,
        ];
    }

    public function external(): static
    {
        return $this->state(fn (array $attributes): array => [
            'class' => BotClass::External,
            'availability' => BotAvailability::Private,
            'external_token' => $attributes['bot_id'].':'.str_repeat('e', 35),
        ]);
    }

    public function privateCatalog(): static
    {
        return $this->state(fn (): array => [
            'availability' => BotAvailability::Private,
        ]);
    }
}
