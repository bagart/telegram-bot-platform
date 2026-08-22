#!/usr/bin/env bash
# Syntax-check every bash entry point (02-developer-tooling.md §12).
# Auto-discovers bash scripts under cmd/, tools/ and deploy/ so new
# commands are covered without editing this list.
set -u
cd "$(dirname "$0")/../.." || exit 1
status=0
while IFS= read -r -d '' f; do
  head -n 1 "$f" | grep -q '^#!.*bash' || continue
  if bash -n "$f"; then
    echo "OK   $f"
  else
    echo "FAIL $f"
    status=1
  fi
done < <(find cmd tools deploy -type f -print0 2>/dev/null)
exit "$status"
