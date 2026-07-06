#!/usr/bin/env bash
# Stop the test site.
#   ./stop.sh          # stop containers, KEEP data (fast restart with ./start.sh)
#   ./stop.sh --wipe   # stop AND delete all data (fresh WordPress next start)
set -uo pipefail
cd "$(dirname "$0")"
if [ "${1:-}" = "--wipe" ]; then
  echo "Stopping and WIPING all data..."
  docker compose down -v
else
  echo "Stopping (data kept)..."
  docker compose down
fi
