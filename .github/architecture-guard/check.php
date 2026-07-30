<?php

/**
 * Architecture Guard V1
 *
 * Validates a git change set against policy.txt.
 * Uses git diff only — no filesystem inventory, no Composer.
 *
 * ALLOWED is the V1 implementation contract. Future versions may
 * replace that list with a generated contract without changing
 * the validation rules below.
 */

$repoRoot = dirname(__DIR__, 2);
$policyPath = $repoRoot . '/.github/architecture-guard/policy.txt';

$failures = array();

/**
 * @return array{
 *   MODE: string,
 *   MAX_FILES: int|null,
 *   FORBIDDEN: string[],
 *   PROTECTED: string[],
 *   ALLOWED: string[]
 * }
 */
function loadPolicy($policyPath)
{
    if (!is_file($policyPath)) {
        fwrite(STDERR, "Architecture Guard failed.\n");
        fwrite(STDERR, "- Policy file missing: .github/architecture-guard/policy.txt\n");
        exit(1);
    }

    $contents = file_get_contents($policyPath);
    if ($contents === false) {
        fwrite(STDERR, "Architecture Guard failed.\n");
        fwrite(STDERR, "- Unable to read policy file.\n");
        exit(1);
    }

    $policy = array(
        'MODE' => 'subset',
        'MAX_FILES' => null,
        'FORBIDDEN' => array(),
        'PROTECTED' => array(),
        'ALLOWED' => array(),
    );

    $section = null;
    $lines = preg_split("/\r\n|\n|\r/", $contents);

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || $trimmed[0] === '#') {
            continue;
        }

        if (preg_match('/^(MODE|MAX_FILES|FORBIDDEN|PROTECTED|ALLOWED)\s*=\s*(.*)$/i', $trimmed, $matches)) {
            $key = strtoupper($matches[1]);
            $value = trim($matches[2]);

            if ($key === 'MODE') {
                $policy['MODE'] = strtolower($value);
                $section = null;
                continue;
            }

            if ($key === 'MAX_FILES') {
                $policy['MAX_FILES'] = (int) $value;
                $section = null;
                continue;
            }

            if ($value !== '') {
                $policy[$key][] = normalizePath($value);
            }

            $section = $key;
            continue;
        }

        $upper = strtoupper($trimmed);
        if (
            $upper === 'MODE'
            || $upper === 'MAX_FILES'
            || $upper === 'FORBIDDEN'
            || $upper === 'PROTECTED'
            || $upper === 'ALLOWED'
        ) {
            $section = $upper;
            continue;
        }

        if ($section === 'MODE') {
            $policy['MODE'] = strtolower($trimmed);
            $section = null;
            continue;
        }

        if ($section === 'MAX_FILES') {
            $policy['MAX_FILES'] = (int) $trimmed;
            $section = null;
            continue;
        }

        if (
            $section === 'FORBIDDEN'
            || $section === 'PROTECTED'
            || $section === 'ALLOWED'
        ) {
            $policy[$section][] = normalizePath($trimmed);
        }
    }

    $policy['FORBIDDEN'] = array_values(array_unique($policy['FORBIDDEN']));
    $policy['PROTECTED'] = array_values(array_unique($policy['PROTECTED']));
    $policy['ALLOWED'] = array_values(array_unique($policy['ALLOWED']));

    return $policy;
}

function normalizePath($path)
{
    return str_replace('\\', '/', trim($path));
}

function pathMatchesRule($path, $rule)
{
    $path = normalizePath($path);
    $rule = normalizePath($rule);

    if ($rule === '') {
        return false;
    }

    if (substr($rule, -1) === '/') {
        return strpos($path, $rule) === 0;
    }

    if ($path === $rule) {
        return true;
    }

    return strpos($path, $rule . '/') === 0;
}

function pathMatchesAny($path, $rules)
{
    foreach ($rules as $rule) {
        if (pathMatchesRule($path, $rule)) {
            return true;
        }
    }

    return false;
}

function isRuntimePath($path)
{
    $path = normalizePath($path);

    if ($path === 'studymentor-content-engine.php') {
        return true;
    }

    if (strpos($path, 'src/') === 0) {
        return true;
    }

    if (strpos($path, 'views/') === 0) {
        return true;
    }

    return false;
}

/**
 * Resolve BASE/HEAD from GitHub Actions event payload or env overrides.
 *
 * @return array{0: string, 1: string}
 */
function resolveRange($repoRoot)
{
    $baseOverride = getenv('ARCHITECTURE_GUARD_BASE');
    $headOverride = getenv('ARCHITECTURE_GUARD_HEAD');

    if (is_string($baseOverride) && $baseOverride !== '' && is_string($headOverride) && $headOverride !== '') {
        return array($baseOverride, $headOverride);
    }

    $eventName = getenv('GITHUB_EVENT_NAME');
    $eventPath = getenv('GITHUB_EVENT_PATH');
    $payload = null;

    if (is_string($eventPath) && $eventPath !== '' && is_file($eventPath)) {
        $raw = file_get_contents($eventPath);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }
    }

    if ($eventName === 'pull_request' && is_array($payload)) {
        $base = isset($payload['pull_request']['base']['sha'])
            ? (string) $payload['pull_request']['base']['sha']
            : '';
        $head = isset($payload['pull_request']['head']['sha'])
            ? (string) $payload['pull_request']['head']['sha']
            : '';

        if ($base !== '' && $head !== '') {
            return array($base, $head);
        }
    }

    if ($eventName === 'push' && is_array($payload)) {
        $base = isset($payload['before']) ? (string) $payload['before'] : '';
        $head = isset($payload['after']) ? (string) $payload['after'] : '';

        if ($base !== '' && preg_match('/^0+$/', $base)) {
            $base = 'origin/main';
        }

        if ($base !== '' && $head !== '') {
            return array($base, $head);
        }
    }

    // workflow_dispatch and local/fallback
    $base = (is_string($baseOverride) && $baseOverride !== '')
        ? $baseOverride
        : 'origin/main';
    $head = (is_string($headOverride) && $headOverride !== '')
        ? $headOverride
        : 'HEAD';

    return array($base, $head);
}

/**
 * @return string[]
 */
function gitChangedFiles($base, $head)
{
    $range = $base . '...' . $head;
    $command = 'git diff --name-only --diff-filter=ACDMRTUXB '
        . escapeshellarg($range)
        . ' 2>&1';

    $output = array();
    $exitCode = 0;
    exec($command, $output, $exitCode);

    if ($exitCode !== 0) {
        // Fallback for cases where triple-dot cannot resolve a merge base.
        $command = 'git diff --name-only --diff-filter=ACDMRTUXB '
            . escapeshellarg($base)
            . ' '
            . escapeshellarg($head)
            . ' 2>&1';
        $output = array();
        $exitCode = 0;
        exec($command, $output, $exitCode);
    }

    if ($exitCode !== 0) {
        fwrite(STDERR, "Architecture Guard failed.\n");
        fwrite(STDERR, "- Unable to compute git diff for range: " . $range . "\n");
        foreach ($output as $line) {
            fwrite(STDERR, '- git: ' . $line . "\n");
        }
        exit(1);
    }

    $files = array();
    foreach ($output as $line) {
        $path = normalizePath($line);
        if ($path !== '') {
            $files[] = $path;
        }
    }

    $files = array_values(array_unique($files));
    sort($files);

    return $files;
}

function isAllowedPath($path, $allowed)
{
    return in_array(normalizePath($path), $allowed, true);
}

chdir($repoRoot);

$policy = loadPolicy($policyPath);

// Future hook: generated contracts can replace ALLOWED without changing validators.
$allowed = $policy['ALLOWED'];

list($base, $head) = resolveRange($repoRoot);
$changed = gitChangedFiles($base, $head);

$mode = $policy['MODE'];
if ($mode !== 'subset' && $mode !== 'exact') {
    $failures[] = 'Unsupported MODE: ' . $mode . ' (expected subset or exact).';
}

if ($policy['MAX_FILES'] === null) {
    $failures[] = 'MAX_FILES is required in policy.txt.';
} elseif (count($changed) > $policy['MAX_FILES']) {
    $failures[] = 'Changed file count '
        . count($changed)
        . ' exceeds MAX_FILES '
        . $policy['MAX_FILES']
        . '.';
}

foreach ($changed as $path) {
    if (pathMatchesAny($path, $policy['FORBIDDEN'])) {
        $failures[] = 'Forbidden path modified: ' . $path;
        continue;
    }

    $onAllowList = isAllowedPath($path, $allowed);

    if (pathMatchesAny($path, $policy['PROTECTED']) && !$onAllowList) {
        $failures[] = 'Protected path modified without permission: ' . $path;
    }

    if (isRuntimePath($path) && !$onAllowList) {
        $failures[] = 'Unexpected runtime file modified: ' . $path;
    }

    if (!$onAllowList && ($mode === 'subset' || $mode === 'exact')) {
        $failures[] = 'Path not listed in ALLOWED contract: ' . $path;
    }
}

if ($mode === 'exact') {
    $missing = array_diff($allowed, $changed);
    foreach ($missing as $path) {
        $failures[] = 'ALLOWED contract path not changed (exact mode): ' . $path;
    }
}

echo "Architecture Guard V1\n";
echo 'Event: ' . (getenv('GITHUB_EVENT_NAME') ?: 'local') . "\n";
echo 'Range: ' . $base . '...' . $head . "\n";
echo 'Mode: ' . $mode . "\n";
echo 'Changed files: ' . count($changed) . "\n";

foreach ($changed as $path) {
    echo '  - ' . $path . "\n";
}

if ($failures !== array()) {
    echo "Architecture Guard failed.\n";
    foreach ($failures as $failure) {
        echo '- ' . $failure . "\n";
    }
    exit(1);
}

echo "Architecture Guard passed.\n";
exit(0);
