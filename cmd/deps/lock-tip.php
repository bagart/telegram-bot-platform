<?php

declare(strict_types=1);

/**
 * Tip-freshness comparator for composer.prod.lock (control lock-tip-fresh:prod,
 * invoked by cmd/deps/check).
 *
 * For every bagart/* entry in `packages` + `packages-dev` it derives the branch
 * from the `dev-<branch>` version and compares the pinned source.reference
 * against the current tip of that branch on origin (`git ls-remote`). A version
 * that does not start with `dev-` is a stable-tag pin — the prefer-stable
 * re-arm a future cmd/release/lib tag reintroduces — and counts as a policy
 * finding instead of a check failure.
 *
 * The remote is derived from the lock's own source.url (normalized to HTTPS),
 * so the control runs in CI checkouts that have no misc/ tree and needs no
 * credentials for the public bagart repositories.
 *
 * Usage:
 *   php cmd/deps/lock-tip.php --lock <file> [--tips <json-map>] [--verbose]
 *
 * --tips injects a { "<package>": "<sha>" } map instead of probing origins;
 * packages absent from the map are reported skipped, never failed.
 *
 * Exit codes (mirror cmd/lib/common.sh — no new codes):
 *   0 = nothing stale and nothing policy-flagged;
 *   1 = stale tip (check failure); 2 = usage error;
 *   3 = git unavailable; 5 = stable-tag pin only (policy failure).
 */

const EX_OK = 0;
const EX_CHECK = 1;
const EX_USAGE = 2;
const EX_DEP = 3;
const EX_POLICY = 5;

/**
 * Compares bagart/* lock pins against origin branch tips (or injected tips).
 */
final class LockTipComparator
{
    /**
     * Origin probe budget per package, seconds (wall clock, kills hangs).
     */
    private const PROBE_TIMEOUT_SECONDS = 20;

    /**
     * @var list<string> buffered verdict lines, printed once judging is done
     */
    private array $lines = [];

    private int $fresh = 0;

    private int $stale = 0;

    private int $hijack = 0;

    private int $skipped = 0;

    /**
     * @param array<string, mixed>|null $tips injected {package: sha} tips;
     *                                         null probes origins with git
     */
    public function __construct(
        private readonly ?array $tips,
        private readonly bool $verbose = false,
    ) {
    }

    public function run(string $lockPath): int
    {
        if ($this->tips === null && !self::gitAvailable()) {
            fwrite(STDERR, "ERROR: required command 'git' not found (origin tips are probed with git ls-remote)\n");

            return EX_DEP;
        }

        $lock = $this->readLock($lockPath);
        if ($lock === null) {
            return EX_USAGE;
        }

        foreach ($this->bagartEntries($lock) as $entry) {
            $this->judge($entry);
        }

        foreach ($this->lines as $line) {
            fwrite(STDOUT, $line."\n");
        }
        fwrite(STDOUT, sprintf(
            "summary: fresh=%d stale=%d hijack=%d skipped=%d\n",
            $this->fresh,
            $this->stale,
            $this->hijack,
            $this->skipped,
        ));

        if ($this->stale > 0) {
            return EX_CHECK;
        }

        return $this->hijack > 0 ? EX_POLICY : EX_OK;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readLock(string $lockPath): ?array
    {
        $raw = @file_get_contents($lockPath);
        if ($raw === false) {
            fwrite(STDERR, "ERROR: lock file not readable: {$lockPath}\n");

            return null;
        }
        $lock = json_decode($raw, true);
        if (!is_array($lock)) {
            fwrite(STDERR, "ERROR: lock file is not valid JSON: {$lockPath}\n");

            return null;
        }

        return $lock;
    }

    /**
     * @param  array<string, mixed> $lock
     * @return list<array{name: string, version: string, reference: string, url: string}>
     */
    private function bagartEntries(array $lock): array
    {
        $entries = [];
        foreach (['packages', 'packages-dev'] as $section) {
            foreach (($lock[$section] ?? []) as $package) {
                if (!is_array($package)) {
                    continue;
                }
                $name = (string) ($package['name'] ?? '');
                if (!str_starts_with($name, 'bagart/')) {
                    continue;
                }
                $source = is_array($package['source'] ?? null) ? $package['source'] : [];
                $entries[] = [
                    'name' => $name,
                    'version' => (string) ($package['version'] ?? ''),
                    'reference' => (string) ($source['reference'] ?? ''),
                    'url' => (string) ($source['url'] ?? ''),
                ];
            }
        }

        return $entries;
    }

    /**
     * @param  array{name: string, version: string, reference: string, url: string} $entry
     */
    private function judge(array $entry): void
    {
        $name = $entry['name'];
        $version = $entry['version'];

        if (!str_starts_with($version, 'dev-')) {
            $this->hijack++;
            $this->lines[] = sprintf(
                'stable-tag-pin:%s: %s pinned instead of a dev branch — prefer-stable beats dev-main; refresh the prod lock (prod-lock-tag-hijack Decision)',
                $name,
                $version,
            );

            return;
        }

        $branch = substr($version, strlen('dev-'));
        if ($branch === '' || $entry['reference'] === '') {
            $this->skip($name, 'lock entry carries no dev- branch or source.reference');

            return;
        }

        $tip = $this->resolveTip($entry, $branch);
        if ($tip === null) {
            return;
        }

        if ($this->sameSha($entry['reference'], $tip)) {
            $this->fresh++;
            if ($this->verbose) {
                $this->lines[] = sprintf('fresh:%s: origin/%s @ %s', $name, $branch, $this->short($tip));
            }

            return;
        }

        $this->stale++;
        $this->lines[] = sprintf(
            'stale-lock-tip:%s: lock @ %s != origin/%s @ %s — run: bash cmd/deps/update --mode=both && bash cmd/deps/check',
            $name,
            $this->short($entry['reference']),
            $branch,
            $this->short($tip),
        );
    }

    /**
     * @param  array{name: string, version: string, reference: string, url: string} $entry
     */
    private function resolveTip(array $entry, string $branch): ?string
    {
        $name = $entry['name'];

        if ($this->tips !== null) {
            $tip = trim((string) ($this->tips[$name] ?? ''));
            if ($tip === '') {
                $this->skip($name, 'not present in --tips map');

                return null;
            }

            return $tip;
        }

        $url = $this->httpsUrl($entry['url']);
        if ($url === null) {
            $this->skip($name, 'no usable source.url in the lock entry');

            return null;
        }

        [$exit, $stdout, $stderr] = $this->lsRemote($url, $branch);
        if ($exit === 124) {
            $this->skip($name, 'ls-remote timed out after '.self::PROBE_TIMEOUT_SECONDS.'s');

            return null;
        }
        if ($exit !== 0) {
            $detail = trim($stderr) !== '' ? trim($stderr) : "git ls-remote exited {$exit}";
            $detail = (string) preg_replace('/\s+/', ' ', $detail);
            $this->skip($name, 'ls-remote failed: '.$detail);

            return null;
        }

        $fields = preg_split('/\s+/', trim($stdout));
        $sha = (string) ($fields[0] ?? '');
        if ($sha === '') {
            $this->skip($name, "origin has no refs/heads/{$branch}");

            return null;
        }

        return $sha;
    }

    private function skip(string $name, string $reason): void
    {
        $this->skipped++;
        $this->lines[] = "skipped:{$name}: {$reason}";
    }

    /**
     * Lock references and remote tips may be full or abbreviated shas.
     */
    private function sameSha(string $lockReference, string $tip): bool
    {
        $locked = strtolower(trim($lockReference));
        $remote = strtolower(trim($tip));
        if ($locked === $remote) {
            return true;
        }
        $shared = min(strlen($locked), strlen($remote));

        return $shared >= 7 && substr($locked, 0, $shared) === substr($remote, 0, $shared);
    }

    private function short(string $sha): string
    {
        return substr(trim($sha), 0, 7);
    }

    /**
     * The lock stores transports git can push over (git@, ssh://); the control
     * only ever reads, so an HTTPS URL keeps it credential-free.
     */
    private function httpsUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, strlen('http://'));
        }
        if (preg_match('#^(?:ssh|git)://#', $url) === 1) {
            $withoutScheme = (string) preg_replace('#^(?:ssh|git)://#', '', $url);

            return 'https://'.(string) preg_replace('#^[^/@]+@#', '', $withoutScheme);
        }
        if (preg_match('#^(?:[^@/]+@)?([^:/]+):(.+)$#', $url, $matches) === 1) {
            return 'https://'.$matches[1].'/'.ltrim($matches[2], '/');
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string, 2: string} exit code, stdout, stderr
     */
    private function lsRemote(string $url, string $branch): array
    {
        $command = [
            'git',
            '-c', 'http.lowSpeedLimit=1',
            '-c', 'http.lowSpeedTime='.self::PROBE_TIMEOUT_SECONDS,
            'ls-remote',
            $url,
            'refs/heads/'.$branch,
        ];

        $environment = getenv();
        if (!is_array($environment)) {
            $environment = [];
        }
        // An auth challenge must fail fast instead of prompting (private repos).
        $environment['GIT_TERMINAL_PROMPT'] = '0';
        $environment['GIT_ASKPASS'] = 'echo';

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes, null, $environment);
        if (!is_resource($process)) {
            return [127, '', 'failed to start git'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + self::PROBE_TIMEOUT_SECONDS;
        $exitCode = null;

        while (true) {
            $read = [];
            foreach ([$pipes[1], $pipes[2]] as $pipe) {
                if (is_resource($pipe) && !feof($pipe)) {
                    $read[] = $pipe;
                }
            }
            if ($read !== []) {
                $write = null;
                $except = null;
                if (@stream_select($read, $write, $except, 0, 200000) !== false) {
                    foreach ($read as $pipe) {
                        $chunk = (string) fread($pipe, 65536);
                        if ($chunk === '') {
                            continue;
                        }
                        if ($pipe === $pipes[1]) {
                            $stdout .= $chunk;
                        } else {
                            $stderr .= $chunk;
                        }
                    }
                }
            } else {
                usleep(50000);
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return [124, $stdout, $stderr];
            }
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return [$exitCode ?? 1, $stdout, $stderr];
    }

    private static function gitAvailable(): bool
    {
        $path = getenv('PATH');
        if ($path === false || $path === '') {
            return false;
        }
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            if ($dir !== '' && is_executable(rtrim($dir, '/').'/git')) {
                return true;
            }
        }

        return false;
    }
}

$lockPath = null;
$tipsPath = null;
$verbose = false;

$arguments = array_slice($argv, 1);
for ($i = 0, $count = count($arguments); $i < $count; $i++) {
    $argument = (string) $arguments[$i];
    if ($argument === '--verbose') {
        $verbose = true;
    } elseif ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, "Usage: php cmd/deps/lock-tip.php --lock <file> [--tips <json-map>] [--verbose]\n");
        exit(EX_OK);
    } elseif ($argument === '--lock' || str_starts_with($argument, '--lock=')) {
        $lockPath = str_starts_with($argument, '--lock=')
            ? substr($argument, strlen('--lock='))
            : (string) ($arguments[++$i] ?? '');
    } elseif ($argument === '--tips' || str_starts_with($argument, '--tips=')) {
        $tipsPath = str_starts_with($argument, '--tips=')
            ? substr($argument, strlen('--tips='))
            : (string) ($arguments[++$i] ?? '');
    } else {
        fwrite(STDERR, "ERROR: Unknown option '{$argument}' (see --help)\n");
        exit(EX_USAGE);
    }
}

if ($lockPath === null || $lockPath === '') {
    fwrite(STDERR, "ERROR: --lock <file> is required (see --help)\n");
    exit(EX_USAGE);
}

$tips = null;
if ($tipsPath !== null && $tipsPath !== '') {
    $rawTips = @file_get_contents($tipsPath);
    if ($rawTips === false) {
        fwrite(STDERR, "ERROR: tips file not readable: {$tipsPath}\n");
        exit(EX_USAGE);
    }
    $tips = json_decode($rawTips, true);
    if (!is_array($tips)) {
        fwrite(STDERR, "ERROR: tips file is not a JSON object: {$tipsPath}\n");
        exit(EX_USAGE);
    }
}

exit((new LockTipComparator($tips, $verbose))->run($lockPath));
