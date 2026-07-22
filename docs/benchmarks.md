# Benchmarks

All benchmarks are in `misc/BAGArt/php-async-kernel-client/commands/` (transport/DNS/stress) and `misc/BAGArt/telegram-bot-lib/commands/` (outbound pipeline). XHProf shell wrappers live in `cmd/`.

## 1. Localhost Transport Benchmark

Raw HTTP transport throughput against a local `public/tg-bench.php` server. Concurrency sweep finds the sweet spot per transport. No pipeline, no queue, no rate limiter.

```
php misc/BAGArt/php-async-kernel-client/commands/benchmark_localhost_transport.php [options]
```

| Option | Default | Description |
|---|---|---|
| `--transport=<list>` | all | ask-socket, curl-multi, guzzle, amphp |
| `--concurrent=<list>` | 8,16,32,64,128 | Concurrency levels (single value disables sweep) |
| `--requests=<n>` | 200 | Total requests per level per run |
| `--runs=<n>` | 3 | Runs averaged |
| `--sleep=<us>` | 500000 | Server delay per request |
| `--keep-alive=<mode>` | no | yes, no, both |
| `--no-warm` | — | Skip warm pass |
| `--quick` | — | Sweep [16,64], 100 req, 1 run |
| `--full` | — | requests=500, keep-alive=both (old defaults) |
| `--host=<host:port>` | — | External target (skips local server) |
| `--port=<n>` | 8080 | Local server port |
| `--warmup=<n\|pct>` | auto (20%) | Warmup requests before measurement |

### XHProf wrapper

```
cmd/xhprof_bench_localhost_transport [options...]
```

Iterates all transports under XHProf. Results in `storage/app/tmp/xhprof/benchmark-localhost-transport/`. Generates `benchmark-result.html`.

| Env | Default | Description |
|---|---|---|
| `TRANSPORT_TIMEOUT` | 3600 | Per-transport timeout (s) |
| `XHPROF_FLAGS` | CPU,MEMORY | XHProf flags |

## 2. DNS Benchmark

Tests DNS adapter resolution speed for all hosts from `currency-sources.php` concurrently. Master/worker architecture.

```
php misc/BAGArt/php-async-kernel-client/commands/benchmark_dns.php [options]
```

| Option | Default | Description                |
|---|---|----------------------------|
| `--adapter=<type>` | master mode | DNS adapter: ask-dns, etc. |
| `--runs=<n>` | 1 | Repeats, median kept       |
| `--format=<text\|json>` | text | Output format              |
| `--timeout=<n>` | 120 | Worker timeout (s)         |
| `--tls=<true\|false>` | true | Enable TLS tests           |
| `--dns-use-tls=<true\|false>` | true | DNS over TLS               |
| `--seed=<n>` | — | Worker RNG seed            |
| `--list` | — | List available adapters    |

Master spawns all workers in parallel; each worker runs in isolation.

### XHProf wrapper

```
cmd/xhprof_bench_dns [--runs=N]
```

Iterates all DNS resolvers under XHProf. Results in `storage/app/tmp/xhprof/benchmark-dns/<adapter>/`.

| Env | Default | Description |
|---|---|---|
| `BENCHMARK_TIMEOUT` | 600 | Per-adapter timeout (s) |
| `XHPROF_FLAGS` | CPU,MEMORY | XHProf flags |

## 3. Transport Benchmark

Real-world benchmark against ~40 currency API URLs. Tests transports end-to-end over the internet. Master/worker architecture.

```
php misc/BAGArt/php-async-kernel-client/commands/benchmark_transport.php [options]
```

| Option | Default | Description |
|---|---|---|
| `--transport=<type>` | master mode | ask-socket, curl-multi, guzzle, amphp |
| `--runs=<n>` | 1 | Repeats, median kept |
| `--format=<text\|json>` | text | Output format |
| `--timeout=<n>` | 120 | Worker timeout (s) |
| `--warmup=<n>` | 0 | Warmup requests before measurement |
| `--keep-alive=0\|1` | 1 | ask-socket keep-alive override |
| `--seed=<n>` | — | Worker RNG seed |
| `--xhprof` | — | Enable XHProf (implies --format=json) |
| `--xhprof_dir=<path>` | storage/.../xhprof | XHProf base directory |
| `--clear` | — | Remove XHProf profiles |
| `--list` | — | List available transports |

### Result renderer

```
php misc/BAGArt/php-async-kernel-client/commands/includes/benchmark_transport/result.php <storage-dir>
```

Renders: ranked table (time, rps, ok, fail, memΔ, score) + per-phase client metrics (ask-socket) + RISKS section (>1.5x time variance across transports).

## 4. Stress Benchmark

Scenarios where transports differ most: DNS overhead, connection churn, large payloads.

```
php misc/BAGArt/php-async-kernel-client/commands/benchmark_stress.php [options]
```

| Option | Default | Description |
|---|---|---|
| `--transport=<list>` | all | ask-socket, curl-multi, guzzle |
| `--scenario=<list>` | all | multi-host, high-conc, large-body, connection-churn |
| `--concurrent=<n>` | per-scenario | Concurrency override |
| `--requests=<n>` | 300 | Total requests per scenario |
| `--runs=<n>` | 3 | Runs averaged |
| `--sleep=<us>` | 100000 | Server delay |
| `--keep-alive=<mode>` | both | yes, no, both |
| `--no-warm` | — | Skip warm pass |
| `--quick` | — | Fewer requests/runs |

## 5. Outbound Pipeline Benchmark

Full outbound daemon pipeline with rate limiting, middleware, circuit breaker.

```
php misc/BAGArt/telegram-bot-lib/commands/outbound-benchmark.php [options]
```

| Option | Default | Description |
|---|---|---|
| `--transport=<type>` | all | curl-multi, guzzle, ask-socket |
| `--rate=<n>` | 30 | Rate limit (req/s) |
| `--duration=<n>` | 5 | Measurement duration (s) per run |
| `--runs=<n>` | 3 | Runs averaged |
| `--warmup=<n>` | 2 | Warmup duration (s) |
| `--host=<host:port>` | localhost | Target host (starts local server if omitted) |
| `--port=<n>` | 8080 | Local server port |
| `--orig` | — | Use real Telegram API (requires --token) |
| `--token=<token>` | — | Bot token for --orig mode |

## Shared benchmark endpoints

- `public/tg-bench.php` — local server target. Params: `sleep` (μs), `body_size` (bytes), `fragment` (bytes for chunked response).
- `misc/BAGArt/php-async-kernel-client/commands/includes/currency-sources.php` — ~40 real-world currency API URLs used by transport and stress benchmarks.

## Result renderers (standalone)

All accept a storage directory and render from saved JSON:

```
php misc/BAGArt/php-async-kernel-client/commands/includes/benchmark_localhost_transport/result.php <dir>
php misc/BAGArt/php-async-kernel-client/commands/includes/benchmark_dns/result.php <dir>
php misc/BAGArt/php-async-kernel-client/commands/includes/benchmark_transport/result.php <dir>
```
