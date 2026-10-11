# ADR-007: Identity and Provisioning

> Status: accepted
> Date: 2026-10-08

## Context

ADR-001 (D9/D15) established Telegram-first identity: `users.telegram_id` is the canonical identity, email is recovery-only, and the primary web login is a magic-link flow delivered through the bot DM ("proxy pattern" in `tgbot-module-proxy`). Two implementation-level gaps were left open by that decision and were grilled together in the `management-admin-rbac` session.

First, magic-link login identification: the proxy pattern works by the caller POSTing an explicit `telegram_user_id`, but on a cold browser the platform does not know who is trying to log in — it must correlate the web session with a Telegram identity before anything can be delivered, and it must also decide which of the many `tg_bots` delivers the message. A user constraint of 2026-09-25 made this harder: the flow must work without publishing any platform URL through Telegram, using Telegram Bot API means only.

Second, telegram-first user provisioning: the host `users` schema (bigint `id`, non-null unique `email`, non-null `password`) cannot accept a Telegram-provisioned account, the proxy `WorkspaceResolver` raw-insert violated that schema on PostgreSQL, and the `['auth', 'verified']` dashboard middleware risked an email-verification loop for users with no email. The platform had to decide what a Telegram-provisioned account *is* in the existing schema before link/provision code could be written.

## Decision

### Device-code login via `/login <code>` (source question: `magic-link-login-identification`, resolved 2026-09-25)

Option 4 (device-code flow, Bot API only), 2026-09-25. Login = browser-created one-time code → user sends it to the bot → bot confirms → browser polls → session issued in that browser. No platform URL is ever published through Telegram; everything between bot and platform is plain Bot API sendMessage/updates. Same challenge machinery doubles as the identity **link** flow: a challenge created from an authenticated web session binds the confirming Telegram account to that user instead of issuing a session.

Rejected alternatives:

- Option 1 (deep-link request / start payload): ruled out by the user constraint — the DM contained `https://host/login/verify?token=...`, i.e. a platform URL published through Telegram.
- Option 2 (numeric telegram id entry, literal proxy pattern): users rarely know their numeric id; delivery silently fails for users with no prior private chat with the chosen bot; posted ids enable DM-spam/enum attempts; still does not answer "which bot sends it".
- Option 3 (identity passed from an already-authenticated surface): only works when the user already has a session somewhere; does not solve cold-browser login, which is the whole point of D15.

Binding obligations: auto-provision of the platform user on first confirmed login (D9 invite pattern); token/code stored sha256-hashed, single-use, TTL 10 min, attempt-limited; status endpoint rate-limited; the "Open bot" button targets a configurable bot username (`config('tg-auth.login_bot_username')`) purely as UX sugar — any menu-enabled bot works, no designated "system bot" required.

### Nullable email/password for Telegram-first accounts (source question: `telegram-first-user-provisioning`, resolved 2026-09-25)

Option 1 (user directive, 2026-09-25: "come up with the decision"), 2026-09-25. A new migration makes `users.email` and `users.password` nullable; Telegram-first accounts are provisioned with `name` + `telegram_id` only and have no email/password until a recovery email is added.

Rejected alternatives:

- Option 2 (synthetic email at provision time): fabricated data in a unique column blocks real emails with the same value forever and looks like a bug in dumps; contradicts the spirit of "email = recovery only"; notification code may try to mail it.
- Option 3 (defer schema decision): provisioning still cannot insert without a value, so the gap is not actually fixed; technical debt in the security-critical path.

Binding obligations: the `verified` middleware needs no change — `App\Models\User` does not implement `MustVerifyEmail`, so Fortify's `EnsureEmailIsVerified` already passes for every user; only email-assuming code paths touched by Phase 1 need a null-safe audit; proxy `WorkspaceResolver` is rewritten to link-or-provision against this schema: no forced `id` (DB-generated), no fabricated email, race-safe against the unique `telegram_id` (catch unique violation → re-select).

## Consequences

- **Schema/domain:** `users.email` and `users.password` become nullable (new migration); `telegram_id` (nullable unique) remains the canonical identity; `email_verified_at` is unused for Telegram-first accounts. `UserFactory`, `app/Models/User.php` fillable/casts, and any email-assuming paths (login-by-email, profile, notifications) must be null-safe where Phase 1 touches them.
- **Fortify/session:** no middleware change required for `verified` (no `MustVerifyEmail`); session issuance for email-less users flows through the device-code challenge; `routes/web.php` `['auth', 'verified']` interaction stays as-is.
- **Proxy module:** `MagicLinkService` / `WorkspaceResolver` raw-insert gap is fixed by the rewrite obligation above; `MagicLinkController`-era explicit-`telegram_user_id` POST path is superseded by the device-code flow for cold-browser login.
- **Menu module:** new `/login <code>` command processor in the menu command chain, challenge store with TTL/attempt hygiene, login page + status poll endpoint, `TgSenderContract` used only for plain Bot API replies (no platform URLs in Telegram). Host `routes/web.php` keeps only platform + tg webhook routes; menu keeps its own auth routes.
- **Config:** `config('tg-auth.login_bot_username')` is introduced as UX-only configuration.
- **Downstream:** Phase 5 invite/auto-provision work reuses the same auto-provision path and menu bot auth surfaces; superadmin break-glass retains email/password.
- **Security posture:** correlation secret never leaves the browser ↔ bot-command channel; replaying a code only confirms the attacker's own attempt (session is issued to the browser holding the challenge id); rate limits apply to challenge creation and the status endpoint.
