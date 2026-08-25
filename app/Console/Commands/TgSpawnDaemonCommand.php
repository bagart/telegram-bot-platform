<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Spawns a Telegram platform daemon script as a detached OS process.
 *
 * Scheduled entries (routes/console.php) point here instead of inline
 * closures so `schedule:list` shows named commands and the spawn logic is
 * testable without cron.
 */
class TgSpawnDaemonCommand extends Command
{
    protected $signature = 'tg:spawn-daemon
                            {daemon : poll|queue_processor|outbound}
                            {--dry-run : Print the command instead of executing it}';

    protected $description = 'Spawn a Telegram platform daemon (detached process) from telegram.schedule config';

    public function handle(): int
    {
        $daemon = (string) $this->argument('daemon');
        $config = (array) config("telegram.schedule.$daemon", []);

        if ($config === []) {
            $this->error("Unknown daemon or empty schedule config: telegram.schedule.$daemon");

            return self::FAILURE;
        }

        if (empty($config['enabled'])) {
            $this->line("telegram.schedule.$daemon is disabled — nothing to spawn.");

            return self::SUCCESS;
        }

        $command = $this->buildCommand($daemon, $config);

        if ($command === null) {
            $this->error("No spawn recipe for daemon: $daemon");

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->line($command);

            return self::SUCCESS;
        }

        exec($command.' > /dev/null 2>&1 &');

        $this->line("Spawned: $daemon");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $config  telegram.schedule.{daemon} entry
     */
    private function buildCommand(string $daemon, array $config): ?string
    {
        $php = PHP_BINARY;
        $script = base_path('misc/BAGArt/telegram-bot-lib/commands').DIRECTORY_SEPARATOR;

        $args = match ($daemon) {
            'poll' => $this->pollArgs($config),
            'queue_processor', 'outbound' => $this->redisArgs($config),
            default => null,
        };

        if ($args === null) {
            return null;
        }

        return $php.' '.escapeshellarg($script.$args['script']).$args['options'];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{script: string, options: string}|null
     */
    private function pollArgs(array $config): array
    {
        $tokenArg = !empty($config['token']) ? ' --token='.escapeshellarg((string) $config['token']) : '';
        $extraOpts = !empty($config['options']) ? ' '.$config['options'] : '';

        return ['script' => 'tg_daemons-daemon.php', 'options' => $tokenArg.$extraOpts];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{script: string, options: string}|null
     */
    private function redisArgs(array $config): array
    {
        $opts = ' --redis-host='.escapeshellarg((string) $config['redis_host'])
            .' --redis-port='.escapeshellarg((string) $config['redis_port'])
            .' --request-queue='.escapeshellarg((string) $config['request_queue']);
        $extraOpts = !empty($config['options']) ? ' '.$config['options'] : '';
        $script = $this->argument('daemon') === 'outbound' ? 'outbound-daemon.php' : 'processor-daemon.php';

        return ['script' => $script, 'options' => $opts.$extraOpts];
    }
}
