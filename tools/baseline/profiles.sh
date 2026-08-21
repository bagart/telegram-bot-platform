#!/usr/bin/env bash
# Profile detection (02-developer-tooling.md, 05-ci-cd-and-release.md §3, 10 §6).
#
# Profiles select which controls apply to this repository. Detection is
# evidence-based: a profile activates only when its trigger exists.
#
# Usage:
#   tools/baseline/profiles.sh [--format=text|json]
# Output (text): space-separated profile names, one line.

set -euo pipefail

FORMAT="text"
[[ "${1:-}" == "--format=json" || "${1:-}" == "--json" ]] && FORMAT="json"

cd "$(dirname "$0")/../.."

PROFILES=()

grep -q '"laravel/framework"' composer.json 2>/dev/null && PROFILES+=("laravel")
[[ -f composer.json ]] && PROFILES+=("php")
[[ -f package.json ]] && PROFILES+=("frontend")
if compgen -G 'docker-compose*.yaml' >/dev/null \
  || compgen -G 'docker-compose*.yml' >/dev/null \
  || compgen -G 'Dockerfile*' >/dev/null; then
  PROFILES+=("docker")
fi
[[ -d misc/BAGArt/php-async-kernel-lib ]] && PROFILES+=("async-runtime")
[[ -d misc/BAGArt/telegram-bot-lib ]] && PROFILES+=("telegram")

if [[ "$FORMAT" == "json" ]]; then
  printf '{"profiles":["%s"]}\n' "$(IFS='","'; echo "${PROFILES[*]:-}")"
else
  echo "${PROFILES[*]:-}"
fi
