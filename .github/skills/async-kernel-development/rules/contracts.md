# Contracts & Strict-Contract Rule

The async kernel lives in `misc/BAGArt/php-async-kernel-lib/src/Contracts/Daemons/`. The contracts are the **only** stable surface across the library boundary — implementations are `final`, behavior is pinned by interfaces.

## Canonical contract inventory

```
Contracts/Daemons/
├── ASKDaemonContract.php          — required of every daemon (startup/shutdown/onError/name)
├── ASKTickableContract.php        — tick(int)/pressure()/isIdle()/queueSize()
├── WithASKTickableContract.php    — tickable(): array  (companion tickables, incl. scheduler)
├── WithASKProducerContract.php    — producer-style daemon (Fiber-resumed)
├── ASKWarmableContract.php        — warm(): void  (lazy-connection hook)
├── ASKShutdownAware.php           — shutdownPriority()/shutdownTimeout()/prepareShutdown()
└── ASKTickableEngineContract.php  — the scheduler contract (enqueue/tick/isIdle)
```

Plus supporting types in `src/`:
- `ASKShutdownContext` — passed to `shutdown()`.
- `Enum/ShutdownPhase` — `RUNNING`, `STOPPING`, `DRAINING`, `FORCING`, `STOPPED`.
- `Exceptions/ASKInterruptException`, `ASKForceShutdownException`, `ASKTechnicalException`, `ASKJobStateTransitionException`.
- `Daemons/ASKShutdownAwareTrait.php` — default impls for `ASKShutdownAware` (priority/timeout); override per daemon.

## The strict-contract rule

**No duck-typing across library boundaries.** Concretely:

- ❌ `if (method_exists($obj, 'flush')) { $obj->flush(); }`
- ❌ `if ($obj instanceof SomeConcreteClass) { ... }` to reach an internal method
- ❌ Adding a public method "for future use" with no caller.

If a caller needs a method, **declare it in the interface**. Capability discovery (e.g. "does this queue support DLQ?") is done via dedicated capability interfaces checked with `instanceof` — that's allowed because the capability is itself a declared contract, e.g.:

```php
if ($this->queue instanceof AtomicDlqQueueContract) {
    $this->queue->pushToDeadLetter($envelope, $reason);
} elseif ($this->dlqFallback !== null) {
    ($this->dlqFallback)($envelope, $reason);
} else {
    // explicit poison-pill logging — never silent loss
}
```

The capability interface (`AtomicDlqQueueContract`, `LeaseRenewableQueueContract`, etc.) is a first-class contract with real callers. This is the legitimate pattern; the anti-pattern is reaching into a concrete class's privates via reflection or `method_exists`.

## Where contracts stop

The async-kernel contracts describe **lifecycle and scheduling** only. Anything about *what* a daemon processes (Telegram API calls, queues, DTOs) belongs to the downstream library's contracts — `BAGArt\TelegramBot\Contracts\Outbound\*`. Don't add outbound-specific methods to an ASK contract; add them to the outbound contract and let the daemon compose both.

## Naming conventions

- Interface prefix: `ASK` (Async Kernel) for kernel contracts; `Tg` / `Outbound` for the Telegram library.
- `With*` prefix = composition contract (`WithASKTickableContract` — "this tickable also exposes companions").
- Capability contracts in the outbound lib are *not* `With*`-prefixed (they're optional capabilities, not wiring).
- Enum cases: `TitleCase` (`ShutdownPhase::DRAINING` is the uppercase-status convention here — follow the existing file).
