# DeadLettersPresent — dead letters accumulating in DLQ

## Symptom
- Alert: `DeadLettersPresent` (`tg_dlq_depth_total > 0` for 15m, critical)
- Visible impact: messages permanently failed to deliver; silent data loss risk grows with every unreplayed entry

## Diagnose
```bash
cmd/ops/queue            # per-bot DLQ channels: tg-dlq:{botId}
tgbm:mcp                 # interactive queue/DLQ inspection tools
cmd/ops/metrics | grep dlq
```
- Classify entries by failure reason before touching anything:
  - 4xx from Telegram (bad chat_id, blocked bot, message not modified) → **poison pill**: replaying will fail forever; fix or drop.
  - 429/5xx/network after retry budget exhausted → transient outage residue: safe to replay once the cause is gone.

## Act
1. Confirm the original cause is resolved (Telegram incident over, dependency restored) — never drain a DLQ into a still-broken pipeline.
2. Replay in capped batches — hard cap 50 per invocation:
   ```bash
   cmd/ops/replay --confirm=replay --count=50
   ```
3. Poison pills: correct the payload if possible, otherwise drop deliberately and record why (chat gone / bot blocked are legitimate terminal states).
4. Watch for the replay itself re-filling the DLQ — that means the "resolved" cause is not resolved.

## Verify
- `tg_dlq_depth_total` returns to 0 (or only justified drops remain); no new DeadLetterEntries appearing; alert clears within one scrape interval.

## Escalation
- Owner: platform-team
- When to page a human: DLQ keeps regrowing after two replay passes, or entries are user-critical and older than 24h
