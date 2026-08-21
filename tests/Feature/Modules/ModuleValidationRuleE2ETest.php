<?php

declare(strict_types=1);

use BAGArt\AsyncKernel\Wrappers\ASKLogWrapper;
use BAGArt\TelegramBot\Configs\TgBotConfig;
use BAGArt\TelegramBot\Configs\TgServiceConfig;
use BAGArt\TelegramBot\Contracts\Outbound\TgSenderContract;
use BAGArt\TelegramBot\Contracts\TgApi\TgApiMethodDTOContract;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\AdvertisingValidationRule;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageValidationRuleRegistry;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageValidatorProcessor;
use BAGArt\TelegramBot\Processing\Processors\MessageValidator\MessageVerdictExecutor;
use BAGArt\TelegramBot\TgApi\Types\DTO\ChatTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\MessageTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\DTO\UserTypeDTO;
use BAGArt\TelegramBot\TgApi\Types\Enum\ChatPropTypeEnum;
use BAGArt\TelegramBot\TgBotSetupFactory;
use Modules\Example\ExampleValidationRule;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

beforeEach(function () {
    config('telegram.modules'); // forces the module config scan
    ExampleValidationRule::$matchedRules = [];
});

function ruleTestMessage(string $text): MessageTypeDTO
{
    return new MessageTypeDTO(
        messageId: 10,
        date: time(),
        chat: new ChatTypeDTO(id: '100', type: ChatPropTypeEnum::GROUP),
        from: new UserTypeDTO(id: '42', isBot: false, firstName: 'Tester'),
        text: $text,
    );
}

/** Sender spy that records classes of sent method DTOs. */
function ruleTestSenderSpy(): TgSenderContract
{
    return new class () implements TgSenderContract {
        /** @var list<class-string> */
        public array $sent = [];

        public function send(TgBotConfig $botConfig, TgApiMethodDTOContract $dto): void
        {
            $this->sent[] = $dto::class;
        }
    };
}

function ruleTestProcessor(TgSenderContract $sender): MessageValidatorProcessor
{
    return new MessageValidatorProcessor(
        ruleRegistry: app(MessageValidationRuleRegistry::class),
        executor: new MessageVerdictExecutor(
            sender: $sender,
            logger: new ASKLogWrapper(new Logger('test', [new NullHandler()])),
        ),
    );
}

it('module rule lands in the shared rule registry next to the core rule (AC-6)', function () {
    $classes = array_map(
        static fn ($rule) => $rule::class,
        iterator_to_array(app(MessageValidationRuleRegistry::class)->rules()),
    );

    expect($classes)->toContain(AdvertisingValidationRule::class);

    // the module rule is registered with a weight, so it sits behind the
    // WeightedMessageValidationRule decorator — probe it functionally
    $trigger = ruleTestMessage(ExampleValidationRule::TRIGGER);
    $moduleRulePresent = false;
    foreach (app(MessageValidationRuleRegistry::class)->rules() as $rule) {
        $verdict = $rule->validate($trigger);
        if ($verdict !== null && $verdict->matchedRule === 'example_rule') {
            $moduleRulePresent = true;
        }
    }

    expect($moduleRulePresent)->toBeTrue();
});

it('the shared rule registry singleton is wired into created bot setups', function () {
    $factory = app(TgBotSetupFactory::class);
    $setup = $factory->create(serviceConfig: new TgServiceConfig());

    expect($setup->messageRules)->toBe(app(MessageValidationRuleRegistry::class));
});

it('module rule rejects a message through MessageValidatorProcessor (AC-6 e2e)', function () {
    $sender = ruleTestSenderSpy();
    $processor = ruleTestProcessor($sender);
    $botConfig = new TgBotConfig(token: 't:token', botId: 'test_bot');

    $processor->process(ruleTestMessage('totally fine text'), $botConfig);
    expect($sender->sent)->toBe([]);
    expect(ExampleValidationRule::$matchedRules)->toBe([]);

    $processor->process(
        ruleTestMessage('check this '.ExampleValidationRule::TRIGGER.' please'),
        $botConfig,
    );

    expect(ExampleValidationRule::$matchedRules)->toBe(['example_rule']);
    expect($sender->sent)->not->toBeEmpty(); // verdict executed: delete + restrict + warning
});
