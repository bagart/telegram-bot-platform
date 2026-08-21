#!/usr/bin/env bash
set -u
cd "$(dirname "$0")/../.." || exit 1
status=0
for f in \
  cmd/dev/check cmd/dev/security cmd/dev/fix cmd/dev/doctor cmd/dev/setup cmd/dev/lint cmd/dev/bench \
  cmd/lib/common.sh cmd/lib/contract.sh \
  tools/git-hooks/pre-commit tools/git-hooks/commit-msg tools/git-hooks/pre-push \
  cmd/git/prepush cmd/git/quick-commit cmd/ci/check cmd/deps/audit \
  cmd/ops/status cmd/ops/health cmd/ops/diagnose \
  cmd/ops/backup cmd/ops/backup-verify cmd/ops/restore cmd/ops/dr-test \
  cmd/ops/queue cmd/ops/restart cmd/ops/replay cmd/ops/drain \
  cmd/ops/deploy cmd/ops/rollback cmd/ops/metrics \
  cmd/release/lib cmd/help; do
  if bash -n "$f"; then
    echo "OK   $f"
  else
    echo "FAIL $f"
    status=1
  fi
done
exit "$status"
