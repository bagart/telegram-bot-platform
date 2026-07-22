# Daemons & Tickables

A daemon is any class implementing `ASKDaemonContract` that runs inside `AsyncKernel`. There are two execution models: **tickable** (the kernel polls `tick()` each loop) and **producer** (the kernel resumes a Fiber that yields). Most daemons are tickable.

## ASKDaemonContract (required of every daemon)

```php
interface ASKDaemonContract
{
    public function onError(\Throwable $e): void;
    public function startup(): void;
    public function shutdown(ASKShutdownContext $context): bool; // true = done, false = still draining
    public function name(): string;
}
```

- `startup()` — called once before the tick loop begins. Lightweight only; no blocking I/O.
- `shutdown()` — returns `true` only when fully drained. Returning `false` keeps the kernel in DRAINING phase until `shutdownTimeout()` elapses, then it moves to FORCING.
- `onError()` — kernel-level error hook. Do not throw from here.

## ASKTickableContract (poll-driven daemons)

```php
interface ASKTickableContract
{
    public function tick(int $systemPressure): void; // one iteration
    public function pressure(): int;   // 100 = design limit, >100 = overloaded
    public function isIdle(): bool;    // may be true before queueSize() hits 0
    public function queueSize(): int;
}
```

**Pressure semantics.** `pressure()` returns relative load, scaled so `100` = the design limit. `>100` (e.g. `1000`) signals a 10× overload to the kernel, which applies backpressure by throttling other tickables. `TgOutboundDaemon::pressure()` computes `(queueSize / 256) * 100` — copy that scaling convention for queue-backed daemons.

**`systemPressure`** is the kernel-aggregated value passed into `tick()`. Use it to decide how much work to pull per tick (e.g. skip popping when pressure is high).

**`isIdle()` vs `queueSize()`.** `isIdle()` may return `true` before `queueSize() === 0` (e.g. all remaining tasks are delayed). The kernel uses `isIdle()` for the fast exit path, so implement it honestly — it must account for in-flight fibers and scheduler state, not just the queue. See `TgOutboundDaemon::isIdle()`:

```php
public function isIdle(): bool
{
    return $this->inflight === []
        && $this->queue->size() === 0
        && $this->scheduler->isIdle();
}
```

## WithASKTickableContract (wiring the scheduler)

A tickable daemon must also declare what the kernel should tick alongside it:

```php
interface WithASKTickableContract
{
    /** @return object[] companion tickables (e.g. [LeaseRenewer, Scheduler]) */
    public function tickable(): array;
}
```

For `TgOutboundDaemon`, `tickable()` returns `[$this->leaseRenewer, $this->scheduler]`. The **scheduler is mandatory** — `tick()` enqueues a `Fiber` per popped task into `$this->scheduler`, and the fiber body (`process` → `pipeline` → send) only executes when the kernel ticks the scheduler. Omit it and tasks stall in memory forever.

## ASKWarmableContract (lazy connection hook)

```php
interface ASKWarmableContract
{
    public function warm(): void;
}
```

`AsyncKernel::addDaemon()` checks `instanceof ASKWarmableContract` and calls `warm()` automatically. This is where you open Redis connections, prime socket pools, etc. **Do not** open connections in the constructor — that breaks the lazy rule and blocks `new` during wiring.

If a daemon needs a warmable dependency (e.g. a pooled HTTP client), either:
1. Make the dependency `ASKWarmableContract` and let `addDaemon()` cascade, or
2. Call `$dependency->warm()` from the daemon's own `warm()`.

## Adding a daemon — the wiring contract

1. The daemon class is `final`, implements the contracts it needs.
2. The caller (CLI command or standalone script) constructs it explicitly with `new`.
3. The caller registers it: `$kernel->addDaemon($daemon)`.
4. The daemon is **never** registered as a singleton in a service provider and **never** auto-resolved by the container. This is the primary extensibility point — see the daemon scripts in `misc/BAGArt/telegram-bot-lib/commands/` (`outbound-daemon.php`, `poller-daemon.php`, etc.).

## Fiber usage inside tick()

The tick body may create `Fiber`s and enqueue them into a scheduler (this is how `TgOutboundDaemon` runs the pipeline without blocking the tick loop):

```php
$fiber = new Fiber(function () use ($envelope): void {
    try {
        $this->process($envelope);
    } finally {
        $this->leaseRenewer->untrack($envelope->deliveryId);
        unset($this->inflight[$envelope->deliveryId]);
    }
});
$this->scheduler->enqueue($fiber);
```

Track in-flight work in a local `$inflight` map so `shutdown()` can report completion accurately (`return $this->inflight === []`).
