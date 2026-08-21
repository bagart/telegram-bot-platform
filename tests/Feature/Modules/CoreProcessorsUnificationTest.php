<?php

declare(strict_types=1);

use BAGArt\TelegramBot\Configs\ProcessorConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Processing\BotProcessorContext;
use BAGArt\TelegramBot\Processing\Processors\MessageDTOEchoToUserProcessor;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageValidatorProcessor;
use BAGArt\TelegramBot\Processing\TypeDTOProcessorRegistry;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgBotSetupFactory;

function coreRegistryClasses(TypeDTOProcessorRegistry $registry): array
{
    $setup = TgBotSetupFactory::build()->create(serviceConfig: new TgServiceConfig());
    $context = BotProcessorContext::fromBotSetup($setup);

    $classes = [];
    foreach ($registry->get(MessageTypeDTO::class, $context) as $processor) {
        $classes[] = $processor::class;
    }

    return $classes;
}

it('webhook registry singleton contains core MessageValidatorProcessor without CLI flags', function () {
    expect(coreRegistryClasses(app(TypeDTOProcessorRegistry::class)))
        ->toContain(MessageValidatorProcessor::class);
});

it('CLI behaviors layer on top of core processors, not instead of them', function () {
    $classes = coreRegistryClasses(
        TgBotSetupFactory::processorRegistry(new ProcessorConfig(echo: true)),
    );

    expect($classes)->toContain(MessageValidatorProcessor::class);
    expect($classes)->toContain(MessageDTOEchoToUserProcessor::class);
});

it('factory default registry equals the core registry', function () {
    $setup = app(TgBotSetupFactory::class)->create(serviceConfig: new TgServiceConfig());
    $context = BotProcessorContext::fromBotSetup($setup);

    $classes = [];
    foreach ($setup->processorRegistry->get(MessageTypeDTO::class, $context) as $processor) {
        $classes[] = $processor::class;
    }

    expect($classes)->toContain(MessageValidatorProcessor::class);
});
