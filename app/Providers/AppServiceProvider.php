<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerSlowQueryLogging();
        $this->registerTgAppThrottles();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Slow-query observability (09 §38–§43): logs queries exceeding the
     * threshold to the `stack` channel. Opt-in via DB_SLOW_QUERY_MS; unset
     * keeps a zero-cost listener away.
     */
    protected function registerSlowQueryLogging(): void
    {
        $threshold = (int) env('DB_SLOW_QUERY_MS', 0);

        if ($threshold <= 0) {
            return;
        }

        DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use ($threshold): void {
            if ($query->time < $threshold) {
                return;
            }

            Log::warning('db.slow_query', [
                'sql' => $query->sql,
                'bindings' => $query->bindings,
                'duration_ms' => $query->time,
                'connection' => $query->connectionName,
            ]);
        });
    }

    /**
     * Named throttle buckets for /tgapp/api/v1/* (menu RFC §27.8): libraries
     * never register rate limiters, the host does — later menu tasks only
     * reference these bucket names.
     */
    protected function registerTgAppThrottles(): void
    {
        RateLimiter::for('tgapp-session', fn (Request $request) => Limit::perMinute(
            (int) config('menu.throttle.session'),
        )->by('sess|'.$request->ip().'|'.substr(hash('sha256', strval($request->input('initData'))), 0, 16)));

        foreach (['read', 'write', 'api'] as $bucket) {
            RateLimiter::for("tgapp-{$bucket}", fn (Request $request) => Limit::perMinute(
                (int) config("menu.throttle.{$bucket}"),
            )->by(self::tgAppAuthedKey($request, $bucket)));
        }
    }

    /** Authenticated buckets key by tg uid; anonymous fallback keys by IP. */
    private static function tgAppAuthedKey(Request $request, string $bucket): string
    {
        $uid = $request->attributes->get('tgapp.user_id');

        return $uid === null
            ? "tgapp|{$bucket}|anon|".$request->ip()
            : "tgapp|{$bucket}|{$uid}";
    }
}
