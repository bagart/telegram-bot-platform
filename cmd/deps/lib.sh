#!/usr/bin/env bash
# Shared helpers for cmd/deps mode-aware tooling (todo.module-dual-mode.md §5).
# Sourced by install/update/check/audit/outdated after contract.sh.

# deps_parse_mode <default> "$@"... — extract --mode=dev|prod|both from the
# argument list (shifting it out), validate it, set MODE. Remaining args are
# left in place for contract_parse_args.
deps_parse_mode() {
  local default="$1"; shift
  MODE="$default"
  local parsed=()
  while [[ $# -gt 0 ]]; do
    case "$1" in
      --mode=dev|--mode=prod|--mode=both)
        MODE="${1#--mode=}"
        shift
        ;;
      --mode)
        [[ $# -lt 2 ]] && fail "--mode requires a value: dev|prod|both"
        case "$2" in dev|prod|both) MODE="$2" ;; *) fail "Invalid --mode '$2' (expected dev|prod|both)" ;; esac
        shift 2
        ;;
      *) parsed+=("$1"); shift ;;
    esac
  done
  POSITIONAL=()
  if (( ${#parsed[@]} > 0 )); then
    POSITIONAL=("${parsed[@]}")
  fi
  return 0
}

PROD_MANIFEST="composer.prod.json"
PROD_LOCK="composer.prod.lock"

require_prod_manifest() {
  [[ -f "$PROD_MANIFEST" ]] || { err "$PROD_MANIFEST not found — prod mode is unavailable"; exit "$EX_DEP"; }
}

# A prod lock must never reference path repositories or symlinked installs:
# servers do not have misc/ (todo.module-dual-mode.md §8).
prod_lock_is_pure() {
  local lock="$1"
  if grep -q 'misc/BAGArt' "$lock"; then
    err "prod lock references path sources (misc/BAGArt) — prod installs must resolve from VCS/packagist only"
    return 1
  fi
  if grep -Eq '"symlink"[[:space:]]*:[[:space:]]*true' "$lock"; then
    err "prod lock contains symlinked packages"
    return 1
  fi
  return 0
}
