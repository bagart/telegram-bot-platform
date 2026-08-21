<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Platform health endpoints (06-runtime-operations.md §39-§40).
 *
 * /health/live  — process is alive; never checks dependencies.
 * /health/ready — process can accept work; checks required dependencies.
 * /health       — deep diagnostics; authenticated only, never public.
 */
class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'live']);
    }

    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $ready = ! in_array(false, $checks, true);

        return response()
            ->json(['status' => $ready ? 'ready' : 'degraded', 'checks' => $checks], $ready ? 200 : 503);
    }

    public function health(): JsonResponse
    {
        abort_unless(auth()->check(), 403, 'Deep diagnostics require authentication.');

        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        return response()->json([
            'status' => ! in_array(false, $checks, true) ? 'healthy' : 'degraded',
            'checks' => $checks,
            'runtime' => [
                'php' => PHP_VERSION,
                'laravel' => app()->version(),
                'environment' => app()->environment(),
                'time' => now()->toIso8601String(),
            ],
            'artifact' => [
                'git_commit' => $this->gitCommit(),
            ],
        ]);
    }

    /**
     * Prometheus text-format exposition (09-observability-and-performance.md).
     * Vendor-neutral: scraped by Prometheus or read by cmd/ops/metrics.
     */
    public function metrics(): \Illuminate\Http\Response
    {
        abort_unless(auth()->check(), 403, 'Metrics require authentication.');

        $dbUp = $this->checkDatabase() ? 1 : 0;
        $redisUp = $this->checkRedis() ? 1 : 0;
        [$queueDepth, $dlqDepth] = $this->queueDepths();
        $tg = $this->telegramCountersLast24h();

        $commit = (string) $this->gitCommit();
        $lines = [
            '# HELP tg_db_up Database reachability.',
            '# TYPE tg_db_up gauge',
            "tg_db_up {$dbUp}",
            '# HELP tg_redis_up Redis reachability.',
            '# TYPE tg_redis_up gauge',
            "tg_redis_up {$redisUp}",
            '# HELP tg_outbound_queue_depth Outbound queue depth.',
            '# TYPE tg_outbound_queue_depth gauge',
            "tg_outbound_queue_depth {$queueDepth}",
            '# HELP tg_dlq_depth_total Dead-letter entries across all bots.',
            '# TYPE tg_dlq_depth_total gauge',
            "tg_dlq_depth_total {$dlqDepth}",
            '# HELP tg_sent_last24h Messages sent in the last 24h (rolling window gauge).',
            '# TYPE tg_sent_last24h gauge',
            "tg_sent_last24h {$tg['sent']}",
            '# HELP tg_retry_rate_limit_last24h Telegram 429 retries in the last 24h.',
            '# TYPE tg_retry_rate_limit_last24h gauge',
            "tg_retry_rate_limit_last24h {$tg['retry_rate_limit']}",
            '# HELP tg_retry_circuit_breaker_last24h Circuit-breaker retries in the last 24h.',
            '# TYPE tg_retry_circuit_breaker_last24h gauge',
            "tg_retry_circuit_breaker_last24h {$tg['retry_circuit_breaker']}",
            '# HELP tg_failed_network_last24h Network failures in the last 24h.',
            '# TYPE tg_failed_network_last24h gauge',
            "tg_failed_network_last24h {$tg['failed_network']}",
            '# HELP tg_failed_fatal_last24h Fatal worker failures in the last 24h.',
            '# TYPE tg_failed_fatal_last24h gauge',
            "tg_failed_fatal_last24h {$tg['failed_fatal']}",
            '# HELP tg_business_error_last24h Business errors (HTTP 4xx class) in the last 24h.',
            '# TYPE tg_business_error_last24h gauge',
            "tg_business_error_last24h {$tg['business_error']}",
            '# HELP tg_dlq_pushed_last24h Messages pushed to DLQ in the last 24h.',
            '# TYPE tg_dlq_pushed_last24h gauge',
            "tg_dlq_pushed_last24h {$tg['dlq_pushed']}",
            '# HELP tg_dlq_retried_last24h DLQ messages replayed in the last 24h.',
            '# TYPE tg_dlq_retried_last24h gauge',
            "tg_dlq_retried_last24h {$tg['dlq_retried']}",
        ];
        if ($commit !== '') {
            $lines[] = '# HELP tg_artifact_commit Deployed artifact.';
            $lines[] = '# TYPE tg_artifact_commit gauge';
            $lines[] = "tg_artifact_commit{commit=\"{$commit}\"} 1";
        }

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    /**
     * Aggregated outbound counters over the last 24 hours, zeroed when stats
     * storage is unavailable (09 §85: core must not depend on a vendor).
     *
     * @return array<string, int>
     */
    protected function telegramCountersLast24h(): array
    {
        $zero = ['sent' => 0, 'retry_rate_limit' => 0, 'retry_circuit_breaker' => 0, 'failed_network' => 0, 'failed_fatal' => 0, 'business_error' => 0, 'dlq_pushed' => 0, 'dlq_retried' => 0];
        try {
            /** @var \BAGArt\TelegramBot\Outbound\TgOutboundStats $stats */
            $stats = app(\BAGArt\TelegramBot\Outbound\TgOutboundStats::class);
            $raw = $stats->getGlobalMetrics(
                now()->subHours(23)->format('YmdH'),
                now()->format('YmdH'),
            );
        } catch (\Throwable) {
            return $zero;
        }

        foreach ($raw as $key => $value) {
            $suffix = (string) substr((string) $key, strpos((string) $key, ':') + 1);
            $map = [
                'sent' => 'sent', 'retry:rate_limit' => 'retry_rate_limit',
                'retry:circuit_breaker' => 'retry_circuit_breaker',
                'failed:network' => 'failed_network', 'failed:fatal' => 'failed_fatal',
                'business_error' => 'business_error', 'dlq_pushed' => 'dlq_pushed',
                'dlq_retried' => 'dlq_retried',
            ];
            if (isset($map[$suffix])) {
                $zero[$map[$suffix]] += (int) $value;
            }
        }

        return $zero;
    }

    /** @return array{0: int, 1: int} */
    protected function queueDepths(): array
    {
        try {
            $redis = Redis::connection();
            $depth = (int) $redis->llen('tg-outbound');
            $dlq = 0;
            $iterator = null;
            do {
                $batch = $redis->scan($iterator, ['MATCH' => 'tg-dlq:*', 'COUNT' => 100]);
                if ($batch !== false) {
                    foreach ($batch as $key) {
                        $dlq += (int) $redis->llen($key);
                    }
                }
            } while ($iterator > 0);

            return [$depth, $dlq];
        } catch (\Throwable) {
            return [0, 0];
        }
    }

    protected function checkDatabase(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function gitCommit(): ?string
    {
        $commit = env('APP_GIT_COMMIT');

        if (is_string($commit) && $commit !== '') {
            return $commit;
        }

        $base = base_path();
        if (is_dir($base.'/.git')) {
            $out = shell_exec('git -C '.escapeshellarg($base).' rev-parse --short HEAD 2>/dev/null');

            return is_string($out) ? trim($out) : null;
        }

        return null;
    }
}
