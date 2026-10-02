<?php

declare(strict_types=1);

namespace Database\Factories;

use BAGArt\TelegramBotManagement\Enums\EntityKind;
use BAGArt\TelegramBotManagement\Models\TgEntity;
use BAGArt\TelegramBotManagement\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TgEntity> */
class TgEntityFactory extends Factory
{
    protected $model = TgEntity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::create([
                'name' => 'Workspace '.fake()->unique()->numberBetween(1, 1_000_000_000),
                'slug' => 'workspace-'.fake()->unique()->numberBetween(1, 1_000_000_000),
            ])->id,
            'kind' => EntityKind::Channel,
            'external_id' => fake()->unique()->numberBetween(-9_999_999_999, -1_000_000_000),
            'bot_id' => null,
            'title' => fake()->sentence(3),
            'username' => null,
            'linked_by' => null,
            'linked_at' => now(),
        ];
    }

    public function channel(): static
    {
        return $this->state(fn (): array => ['kind' => EntityKind::Channel]);
    }

    public function chatGroup(): static
    {
        return $this->state(fn (): array => ['kind' => EntityKind::ChatGroup]);
    }

    public function bot(string $botId): static
    {
        return $this->state(fn (): array => [
            'kind' => EntityKind::Bot,
            'bot_id' => $botId,
            'external_id' => null,
        ]);
    }
}
