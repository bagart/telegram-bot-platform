# Monitoring stack (09 §44–§54, §70–§79)

Self-contained Prometheus + Grafana + node-exporter artifact. Running it on
a host is an INFRA decision; everything code-side ships in this directory.

## Bring up

```bash
echo "$(openssl rand -hex 16)" > deploy/monitoring/grafana/admin-password.txt
docker compose -f deploy/monitoring/docker-compose.yml up -d
```

- Grafana: http://127.0.0.1:3000 (admin / generated password)
- Prometheus: http://127.0.0.1:9090
- Alert rules: mounted from `tools/baseline/prometheus-alerts.example.yml`
  (single definition site — tune there, restart prometheus).
- Dashboard: mounted from `tools/baseline/grafana-dashboard.example.json`.

## App metrics

The Laravel `/health/metrics` endpoint requires authentication by design, so
the stack consumes app series via the node-exporter textfile collector:

```bash
# cron */1:
deploy/monitoring/export-app-metrics.sh
```

`cmd/ops/metrics` output lands in `textfile/tg-app.prom`; Prometheus scrapes
it together with host metrics.

## Retention & self-monitoring (§70–§79)

- Prometheus data lives in the `prom-data` volume; set `--storage.tsdb.retention.time=30d`
  in the compose command when you need a hard cap.
- Watch `up{job="app-textfile"}` staleness — a frozen `tg-app.prom` means the
  exporter cron died.
