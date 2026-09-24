#!/usr/bin/env bash
# Runs a command; on failure, posts the last 60 lines of output as a GitHub
# annotation so the failure is readable without log-download rights.
set -uo pipefail
log="$(mktemp)"
"$@" 2>&1 | tee "$log"
status=${PIPESTATUS[0]}
if [ "$status" -ne 0 ]; then
  body="$(tail -n 60 "$log" | sed -e 's/\x1b\[[0-9;]*m//g' -e 's/%/%25/g' -e 's/\r//g' | awk '{printf "%s%%0A", $0}')"
  echo "::error title=$1 failed::${body}"
else
  summary="$(grep -E '^(OK|Tests:|OK, but)' "$log" | tail -n 3 | sed -e 's/\x1b\[[0-9;]*m//g' | awk '{printf "%s%%0A", $0}')"
  [ -n "$summary" ] && echo "::notice title=$1 summary::${summary}"
fi
exit "$status"
