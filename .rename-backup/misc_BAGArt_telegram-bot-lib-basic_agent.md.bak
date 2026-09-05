# Telegram Bot Basic Lib — Architecture Context

We are working on `bagart/telegram-bot-basic-lib`.

This library provides Artisan commands for Telegram bot operations:
long polling, webhook management, identity checks, interactive chatting, and demo commands.

It depends on `telegram-bot-lib` for all bot API interaction, transport, and processing infrastructure. It has NO
models, NO migrations, NO routes.

All commands are registered via `TelegramBotBasicServiceProvider`.

---

# Library's place in the ecosystem

```
Laravel App
        │
        ▼
telegram-bot-management  (multi-bot commands, models)
        │
        ▼
telegram-bot-basic-lib  (single-bot artisan commands, traits)
        │
        ▼
telegram-bot-lib  (bot API, processing, outbound, webhook)
```

---

# Commands

### Core Commands

| Command           | Signature    | Purpose                                 |
|-------------------|--------------|-----------------------------------------|
| TgPollerCommand   | `tg:poll`    | Long-polling daemon with TgPollerDaemon |
| WebhookCommand    | `tg:webhook` | Set or delete webhook URL               |
| TgWhoamiCommand   | `tg:whoami`  | Call getMe to verify bot identity       |
| TgChattingCommand | `tg:chat`    | Interactive terminal chat with the bot  |

### Demo / Example Commands

| Command                           | Signature                     | Purpose                           |
|-----------------------------------|-------------------------------|-----------------------------------|
| DemoSendPollCommand               | `tg:demo:poll`                | Send a poll via the bot           |
| ExampleSequentialCommand          | `tg:example:sequential`       | Sequential API calls demo         |
| ExampleParallelFuturesCommand     | `tg:example:parallel-futures` | Parallel execution with futures   |
| ExampleParallelBatchCommand       | `tg:example:parallel-batch`   | Batch parallel processing demo    |
| ExampleAllVariantsCommand         | `tg:example:all`              | All transport variants comparison |
| ExampleTransportComparisonCommand | `tg:example:transport`        | Transport benchmarking            |

---

# Traits

### TokenResolverTrait

Resolves bot token from `--token` option or env (`TELEGRAM_BOT_TOKEN`). Validates format: `/^\d+:[A-Za-z0-9_-]+$/`.

Used by: TgPollerCommand, TgWhoamiCommand, TgChattingCommand, TgBotManagerInit.

### ArtisanExtraTrait

Helper trait for Artisan command options and output formatting.

### LongPollingCommandTrait

Shared long-polling setup logic: creates `TgBotConfig`, `TgPollerConfig`,
`TgServiceConfig`, and starts `TgPollerDaemon`.

---

# Service Provider

`TelegramBotBasicServiceProvider` registers only Artisan commands. Does NOT load routes, migrations, or models.

---

# Testing

Tests use Pest PHP with pure unit/model inspection pattern (no DB).

Existing: WebhookCommandTest, ArtisanExtraTraitTest, TokenResolverTraitTest.
