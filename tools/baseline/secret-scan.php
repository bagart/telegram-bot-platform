<?php

declare(strict_types=1);

/**
 * Built-in secret scanner (03-security-and-supply-chain.md §6, 10-telegram-platform-and-libraries.md §3).
 *
 * Scans repository files for credential patterns, including the Telegram bot
 * token format. Supports a narrow allowlist (rule + path + reason + expiry);
 * expired allowlist entries are a policy failure (exit 5).
 *
 * Usage:
 *   php tools/baseline/secret-scan.php --staged|--all|--paths=a,b [--format=text|json]
 *
 * Exit codes: 0 clean, 1 secret found, 2 usage error, 5 expired allowlist entry.
 */

const EXIT_OK = 0;
const EXIT_CHECK = 1;
const EXIT_USAGE = 2;
const EXIT_POLICY = 5;

const MAX_FILE_SIZE = 512 * 1024;

const BINARY_EXTENSIONS = [
    'png', 'jpg', 'jpeg', 'gif', 'ico', 'webp', 'woff', 'woff2', 'ttf', 'eot',
    'otf', 'pdf', 'zip', 'gz', 'mp3', 'mp4', 'sqlite', 'phar', 'map',
];

const PATTERNS = [
    'telegram-bot-token' => '/\b[0-9]{5,}:[A-Za-z0-9_-]{30,}\b/',
    'aws-access-key' => '/\bAKIA[0-9A-Z]{16}\b/',
    'github-token' => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
    'google-api-key' => '/\bAIza[0-9A-Za-z_-]{35}\b/',
    'private-key-block' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    'slack-webhook' => '/https:\/\/hooks\.slack\.com\/services\/T[A-Za-z0-9_]+\/B[A-Za-z0-9_]+\/[A-Za-z0-9]+/',
    'generic-secret-assignment' => '/\b(api[_-]?key|secret|password|passwd|pwd|auth[_-]?token|access[_-]?token)\b\s*[:=]\s*[\'"][A-Za-z0-9+\/=_\-]{16,}[\'"]/i',
];

$format = 'text';
$mode = null;
$explicitPaths = [];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--staged' || $arg === '--all') {
        $mode = substr($arg, 2);
    } elseif (str_starts_with($arg, '--paths=')) {
        $mode = 'paths';
        $explicitPaths = array_filter(explode(',', substr($arg, 8)));
    } elseif ($arg === '--format=json') {
        $format = 'json';
    } elseif ($arg === '--format=text') {
        $format = 'text';
    } elseif ($arg === '--json') {
        $format = 'json';
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\nUsage: php tools/baseline/secret-scan.php --staged|--all|--paths=a,b [--format=text|json]\n");
        exit(EXIT_USAGE);
    }
}

if ($mode === null) {
    fwrite(STDERR, "Mode required: --staged, --all or --paths=...\n");
    exit(EXIT_USAGE);
}

$repoRoot = findRepoRoot();
if ($repoRoot === null) {
    fwrite(STDERR, "Unable to locate repository root (no .git directory found)\n");
    exit(EXIT_USAGE);
}

$files = match ($mode) {
    'staged' => gitStagedFiles($repoRoot),
    'all' => gitTrackedFiles($repoRoot),
    'paths' => $explicitPaths,
};

$allowlist = loadAllowlist("{$repoRoot}/tools/baseline/secret-allowlist.json");
$expired = array_filter($allowlist, fn (array $e): bool => $e['expires'] !== null && $e['expires'] < date('Y-m-d'));

$findings = [];
foreach ($files as $relative) {
    $absolute = "{$repoRoot}/{$relative}";
    if (!is_file($absolute) || isSkippable($absolute)) {
        continue;
    }
    $contents = file_get_contents($absolute);
    if ($contents === false || str_contains($contents, "\0")) {
        continue;
    }
    foreach (PATTERNS as $rule => $pattern) {
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            continue;
        }
        foreach ($matches[0] as $match) {
            $path = str_replace('\\', '/', $relative);
            if (isAllowlisted($allowlist, $rule, $path)) {
                continue;
            }
            $line = substr_count(substr($contents, 0, $match[1]), "\n") + 1;
            $findings[] = [
                'rule' => $rule,
                'path' => $path,
                'line' => $line,
                'excerpt' => maskExcerpt($match[0]),
            ];
        }
    }
}

if ($format === 'json') {
    echo json_encode([
        'exit_code' => $expired !== [] ? EXIT_POLICY : ($findings === [] ? EXIT_OK : EXIT_CHECK),
        'scanned_files' => count($files),
        'expired_allowlist' => array_values(array_map(fn (array $e): array => [
            'rule' => $e['rule'], 'path' => $e['path'], 'expires' => $e['expires'],
        ], $expired)),
        'findings' => $findings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} else {
    foreach ($expired as $entry) {
        fwrite(STDERR, sprintf(
            "POLICY FAILURE: expired allowlist entry rule=%s path=%s expired=%s — renew with explicit approval or remove\n",
            $entry['rule'],
            $entry['path'],
            $entry['expires'],
        ));
    }
    foreach ($findings as $finding) {
        fwrite(STDERR, sprintf(
            "SECRET DETECTED rule=%s path=%s:%d — revoke, rotate, remove from history; narrow allowlist only if justified\n",
            $finding['rule'],
            $finding['path'],
            $finding['line'],
        ));
    }
    if ($findings === [] && $expired === []) {
        fwrite(STDERR, sprintf("Secret scan passed (%d files).\n", count($files)));
    }
}

exit($expired !== [] ? EXIT_POLICY : ($findings === [] ? EXIT_OK : EXIT_CHECK));

function findRepoRoot(): ?string
{
    $dir = getcwd();
    while ($dir !== false && !is_dir("{$dir}/.git")) {
        $parent = dirname($dir);
        if ($parent === $dir) {
            return null;
        }
        $dir = $parent;
    }

    return $dir ?: null;
}

/**
 * @return string[]
 */
function gitStagedFiles(string $root): array
{
    return gitFileList($root, ['diff', '--cached', '--name-only', '--diff-filter=ACM']);
}

/**
 * @return string[]
 */
function gitTrackedFiles(string $root): array
{
    return gitFileList($root, ['ls-files']);
}

/**
 * @param string[] $args
 * @return string[]
 */
function gitFileList(string $root, array $args): array
{
    $cmd = array_merge(['git', '-C', $root], $args, ['-z']);
    $pipe = popen(implode(' ', array_map('escapeshellarg', $cmd)), 'r');
    $out = stream_get_contents($pipe);
    pclose($pipe);

    return $out === false ? [] : array_filter(explode("\0", $out));
}

/**
 * @return array<int, array{rule: string, path: string, reason: string, expires: ?string}>
 */
function loadAllowlist(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_map(fn (array $e): array => [
        'rule' => (string) ($e['rule'] ?? '*'),
        'path' => (string) ($e['path'] ?? '*'),
        'reason' => (string) ($e['reason'] ?? ''),
        'expires' => isset($e['expires']) ? (string) $e['expires'] : null,
    ], $decoded));
}

function isAllowlisted(array $allowlist, string $rule, string $path): bool
{
    foreach ($allowlist as $entry) {
        $ruleMatch = $entry['rule'] === '*' || $entry['rule'] === $rule;
        $pathMatch = $entry['path'] === '*' || fnmatch($entry['path'], $path) || str_starts_with($path, rtrim($entry['path'], '/') . '/');
        if ($ruleMatch && $pathMatch) {
            return true;
        }
    }

    return false;
}

function isSkippable(string $absolute): bool
{
    if (filesize($absolute) > MAX_FILE_SIZE) {
        return true;
    }
    $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

    return in_array($ext, BINARY_EXTENSIONS, true);
}

function maskExcerpt(string $excerpt): string
{
    $length = strlen($excerpt);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }

    return substr($excerpt, 0, 4) . str_repeat('*', $length - 8) . substr($excerpt, -4);
}
