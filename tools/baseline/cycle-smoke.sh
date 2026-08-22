#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
export REPO_ROOT="$PWD"
source cmd/lib/common.sh
source cmd/lib/output.sh
source cmd/lib/contract.sh
source cmd/lib/engine.sh
FORMAT=text QUIET=0 RESUME=0 VERBOSE=0
echo MARK-1
engine_register x y true
engine_register y x true
echo MARK-2
set +e
engine_execute >/tmp/cycle-out.txt 2>&1
rc=$?
set -e
echo "MARK-3 rc=$rc"
cat /tmp/cycle-out.txt
