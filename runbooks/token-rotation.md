# Token rotation — bot token compromised or routine rotation

## Symptom
- Trigger: BotFather token revoked/leaked (`/revoke`), suspected leak, or scheduled rotation
- Visible impact if mishandled: webhooks fail secret validation, bots go dark, daemon auth errors spam logs

## Diagnose / Precheck
- Tokens live in DB (`tg_bots`), never in `.env` — confirm which bots are affected before touching anything.
- Note current webhook state per bot: Telegram keeps the last webhook; changing the token does NOT clear it, but the old secret header becomes invalid.

## Act
1. In @BotFather revoke the old token (`/revoke`) → new token issued.
2. Store the new token immediately: update the bot row (`tgbm:init` flow or direct DB update on `tg_bots.token`); do not commit tokens anywhere.
3. Re-register the webhook with the new credentials:
   ```bash
   php artisan tg:tg_webhook --token=<NEW_TOKEN> --secret-token=<SECRET>
   ```
   Keep the same URL/IP constraints as before; add `--drop-pending` only if a poisoned update backlog is suspected.
4. Restart long-polling/daemon workers that cache the old token (`tgbm:poll`, `tgbm:outbound-daemon`) via graceful shutdown — wait for drain instead of killing.
5. If the secret-token header itself is rotated: every webhook registration must be updated in the same sitting, otherwise IP-valid requests start failing secret validation.

## Verify
- `php artisan tg:tg_webhook --token=<NEW_TOKEN>` (info output) shows the expected webhook + secret accepted.
- `/health` green; send a test message to the bot and confirm it lands (`tgbm:monitor`).

## Escalation
- Owner: platform-team
- When to page a human: leaked token was for a production bot with active users — rotate out-of-hours with a rollback plan (old token stops working the moment it is revoked)
