#!/usr/bin/env bash
# Host-side process watchdog (06-runtime-operations.md §47–§52).
#
# Samples RSS / open FDs / threads of a target PID and writes Prometheus
# textfile metrics for node_exporter. Exits non-zero when thresholds are
# breached so cron mail / alerting picks it up. Kernel-internal gauges
# (event-loop lag etc.) remain KERNEL-WIP; this covers the host view.
#
# Usage:
#   tools/ops/watchdog.sh --pid <pid> [--interval=10] [--once]
# Env:
#   WATCHDOG_RSS_MB_MAX   default 1024
#   WATCHDOG_FD_MAX       default 4096
#   WATCHDOG_OUT          default storage/app/baseline/watchdog.prom

set -euo pipefail

cd "$(dirname "$0")/../.."

PID="" INTERVAL=10 ONCE=0
while [[ $# -gt 0 ]]; do
  case "$1" in
    --pid) PID="${2:-}"; shift 2 ;;
    --interval) INTERVAL="${2:-10}"; shift 2 ;;
    --once) ONCE=1; shift ;;
    *) echo "Unknown argument: $1" >&2; exit 2 ;;
  esac
done
[[ "$PID" =~ ^[0-9]+$ ]] || { echo "Usage: watchdog.sh --pid <pid>"; exit 2; }
[[ -d "/proc/$PID" ]] || { echo "PID $PID not running" >&2; exit 1; }

RSS_MAX="${WATCHDOG_RSS_MB_MAX:-1024}"
FD_MAX="${WATCHDOG_FD_MAX:-4096}"
OUT="${WATCHDOG_OUT:-storage/app/baseline/watchdog.prom}"
mkdir -p "$(dirname "$OUT")"

sample() {
  local rss_kb fds threads status=0
  rss_kb="$(awk '/VmRSS/{print int($2)}' "/proc/$PID/status" 2>/dev/null)" || status=1
  fds="$(find "/proc/$PID/fd" -maxdepth 1 2>/dev/null | wc -l)"
  threads="$(awk '/Threads/{print int($2)}' "/proc/$PID/status" 2>/dev/null)" || status=1

  {
    echo "# HELP proc_rss_mb Resident set size in MB."
    echo "# TYPE proc_rss_mb gauge"
    echo "proc_rss_mb{pid=\"$PID\"} $(( rss_kb / 1024 ))"
    echo "# HELP proc_open_fds Open file descriptors."
    echo "# TYPE proc_open_fds gauge"
    echo "proc_open_fds{pid=\"$PID\"} ${fds}"
    echo "# HELP proc_threads Thread count."
    echo "# TYPE proc_threads gauge"
    echo "proc_threads{pid=\"$PID\"} ${threads}"
  } >"$OUT.tmp" && mv "$OUT.tmp" "$OUT"

  (( rss_kb / 1024 > RSS_MAX )) && { echo "WATCHDOG BREACH: RSS $((rss_kb/1024))MB > ${RSS_MAX}MB" >&2; status=1; }
  (( fds > FD_MAX )) && { echo "WATCHDOG BREACH: FDs ${fds} > ${FD_MAX}" >&2; status=1; }
  return "$status"
}

if [[ "$ONCE" -eq 1 ]]; then
  sample
  exit $?
fi

while true; do
  if ! sample; then
    exit 1
  fi
  sleep "$INTERVAL"
done
