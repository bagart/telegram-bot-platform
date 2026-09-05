#!/usr/bin/env bash
# Delegates to bagart/telegram-platform-devops-baseline (Phase 4 cutover; retire in Phase 7).
ENGINE="${BASELINE_DIR:-$(cd "$(dirname "$0")/../../vendor/bagart/telegram-platform-devops-baseline" && pwd)}"
exec bash "$ENGINE/controls/shell-lint.sh" "$@"
