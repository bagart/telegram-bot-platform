#!/usr/bin/env bash
# Profile detection (02-developer-tooling.md, 05-ci-cd-and-release.md §3, 10 §6).
#
# Profiles select which controls apply to this repository. Detection is
# evidence-based: a profile activates only when its trigger exists.
#
# Profile composition (10 §6): consumers may request --with-implied so an
# active profile implies the relevant parts of the profiles it composes with
# instead of duplicating them — currently telegram implies async-runtime and
# laravel.
#
# Usage:
#   tools/baseline/profiles.sh [--with-implied] [--format=text|json]
# Output (text): space-separated profile names, one line.

set -euo pipefail

FORMAT="text"
IMPLIED=0
for arg in "$@"; do
  case "$arg" in
    --format=json|--json) FORMAT="json" ;;
    --format=text) FORMAT="text" ;;
    --with-implied) IMPLIED=1 ;;
    --help|-h) echo "usage: profiles.sh [--with-implied] [--format=text|json]"; exit 0 ;;
    *) echo "unknown argument: $arg" >&2; exit 2 ;;
  esac
done

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

has_profile() {
  local wanted="$1" p
  for p in "${PROFILES[@]:-}"; do
    [[ "$p" == "$wanted" ]] && return 0
  done
  return 1
}

if [[ "$IMPLIED" -eq 1 ]]; then
  if has_profile telegram; then
    has_profile laravel || PROFILES+=("laravel")
    has_profile async-runtime || PROFILES+=("async-runtime")
  fi
fi

if [[ "$FORMAT" == "json" ]]; then
  list="["
  first=1
  for p in "${PROFILES[@]:-}"; do
    (( first )) || list+=","
    first=0
    list+="\"$p\""
  done
  printf '{"profiles":%s]}\n' "$list"
else
  echo "${PROFILES[*]:-}"
fi
