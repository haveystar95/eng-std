#!/usr/bin/env bash
#
# Stamp the build: write the current commit into storage/app/commit, which is what
# `GET /api/v1/health` and the plan's `versions.build` answer with.
#
#   backend2/scripts/stamp-build.sh            # stamps HEAD of the repository one level up
#   COMMIT=abc1234 backend2/scripts/stamp-build.sh
#
# The app container mounts backend2/ only, and `.git` lives in the repository root, so the
# container itself cannot ask git — the stamp is written from the host at deploy time.
set -euo pipefail

cd "$(dirname "$0")/.."

COMMIT="${COMMIT:-$(git -C .. rev-parse --short HEAD)}"
mkdir -p storage/app
printf '%s\n' "$COMMIT" >storage/app/commit
echo "stamped: $COMMIT → storage/app/commit"
