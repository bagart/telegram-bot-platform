# Models & Migrations

All three models live in `telegram-bot-management/src/Models/`. Migrations in `telegram-bot-management/database/migrations/`.

## TgBot — the central bot record

```php
class TgBot extends Model
{
    use HasTimestamps;

    protected $primaryKey = 'bot_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['bot_id', 'token', 'secret_token'];

    // CRITICAL: never expose tokens in serialization
    protected $hidden = ['token', 'secret_token'];

    public function owners(): BelongsToMany  // via tg_bot_owners, fk bot_id → user_id
    public function modules(): BelongsToMany // via tg_bot_modules, fk bot_id → chat_id
}
```

Key points:
- **Non-incrementing string PK** (`bot_id`). Usually the numeric bot ID from BotFather (the part before the colon in the token).
- `token` and `secret_token` are `hidden` — they never appear in `toArray()`/JSON output. Preserve this invariant whenever you touch the model.
- `secret_token` is nullable — older bots may not have one set yet.

## TgBotOwner — who owns a bot

```php
class TgBotOwner extends Model
{
    use HasTimestamps;
    use HasUuids;

    protected $primaryKey = 'tg_bot_owner_uuid';
    // ... fillable, etc.
}
```

UUID primary key (`tg_bot_owner_uuid`) via the `HasUuids` trait. Linked to `TgBot` through the `tg_bot_owners` pivot (`bot_id` ↔ `user_id`).

## TgBotModule — a chat/thread a bot serves

```php
class TgBotModule extends Model
{
    use HasTimestamps;
    use HasUuids;

    protected $primaryKey = 'tg_bot_module_uuid';

    protected $fillable = [
        'tg_bot_module_uuid',
        'tg_bot_uuid',
        'chat_id',
        'message_thread_id',
        'module_names',
    ];

    protected $casts = [
        'module_names' => 'array',  // string[] — JSON column
    ];
}
```

- UUID PK (`tg_bot_module_uuid`).
- `chat_id` (int) + `message_thread_id` (int) identify the destination.
- `module_names` is a JSON-cast `string[]` — the set of feature modules active for this chat/thread.
- Linked to `TgBot` via the `tg_bot_modules` pivot (`bot_id` ↔ `chat_id`).

## Relationships summary

```
TgBot (bot_id)
 ├─< tg_bot_owners  >─ TgBotOwner (tg_bot_owner_uuid)
 └─< tg_bot_modules >─ TgBotModule (tg_bot_module_uuid)
```

Both are `BelongsToMany` (many-to-many): a bot can have multiple owners, and an owner can co-own multiple bots; same for modules.

## Migrations

Located in `telegram-bot-management/database/migrations/`:
- `create_tg_bots_table` — `bot_id` (PK), `token`, `secret_token` (nullable), `timestampsTz()`
- `create_tg_bot_modules_table`
- `create_tg_bot_owners_table`

Loaded via `loadMigrationsFrom(__DIR__.'/../database/migrations')` in `TelegramBotManagementServiceProvider`. Run them with `php artisan tgbm:migrate` or the standard `php artisan migrate`.

## Creating a bot (correct way)

Per the tokens-in-DB rule, you create a bot by inserting a row — not by setting an env var:

```php
TgBot::create([
    'bot_id' => $botId,        // numeric ID from BotFather
    'token' => $token,         // full "123:ABC..." token
    'secret_token' => $secret, // tg_webhook secret; nullable
]);
```

For tests, use a factory. (Note: no factory exists yet for these models — adding one is a reasonable enhancement; follow the Laravel `pest-testing` conventions.)
