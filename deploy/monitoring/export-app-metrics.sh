#!/usr/bin/env bash
# Push application metrics into the node_exporter textfile collector so the
# Prometheus/Grafana stack sees tg_* series without exposing an unauthed
# metrics endpoint (09-observability-and-performance.md §70–§79).
# Schedule via cron (see tools/baseline/crontab.example).

set -euo pipefail
cd "$(dirname "$0")/../.."

OUT_DIR="deploy/monitoring/textfile"
mkdir -p "$OUT_DIR"

if bash cmd/ops/metrics >"$OUT_DIR/tg-app.prom.tmp" 2>/dev/null; then
  mv "$OUT_DIR/tg-app.prom.tmp" "$OUT_DIR/tg-app.prom"
else
  rm -f "$OUT_DIR/tg-app.prom.tmp"
  echo "export-app-metrics: collection failed — keeping last good snapshot" >&2
  exit 1
fi
