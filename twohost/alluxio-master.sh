#!/usr/bin/env bash
set -euo pipefail
# One-time format only. Persist this marker with the journal volume.
if [ ! -f /journal/.phat-initialized ]; then
  alluxio formatMaster
  touch /journal/.phat-initialized
fi
exec /entrypoint.sh master-only --no-format
