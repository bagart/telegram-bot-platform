<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
}
