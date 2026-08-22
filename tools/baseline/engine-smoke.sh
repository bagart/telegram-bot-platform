#!/usr/bin/env bash
# Functional smoke test for the control engine (not part of check gates).
set -euo pipefail
cd "$(dirname "$0")/../.."
export REPO_ROOT="$PWD"
source cmd/lib/common.sh
source cmd/lib/output.sh
source cmd/lib/contract.sh
source cmd/lib/engine.sh

FORMAT=text QUIET=0 RESUME=0 VERBOSE=0

ctl_a() { echo a-ok; }
ctl_b() { echo b-ok; }
ctl_slow() { sleep 0.3; }
engine_register "a" "" ctl_a
engine_register "b" "a" ctl_b
engine_register "slow" "a" ctl_slow

if engine_execute; then
  echo "ENGINE: pass"
else
  echo "ENGINE: fail"
  exit 1
fi

# Cycle detection must fail.
(
  FORMAT=text
  ENGINE_IDS=(); ENGINE_DEPS=(); ENGINE_CMDS=()
  engine_register x y true
  engine_register y x true
  engine_execute >/dev/null 2>&1 && echo "CYCLE: not detected" || echo "CYCLE: detected"
)
