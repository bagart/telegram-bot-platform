<?php

declare(strict_types=1);

// Delegates to bagart/telegram-devops-baseline (Phase 4 cutover; retire in Phase 7).
$engine = getenv('BASELINE_DIR') ?: dirname(__DIR__, 2).'/vendor/bagart/telegram-devops-baseline';
require $engine.'/controls/github-policy.php';
