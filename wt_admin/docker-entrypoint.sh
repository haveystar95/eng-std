#!/bin/sh
# Runs from nginx's /docker-entrypoint.d/ before the server starts. Regenerates the
# SPA's runtime config from environment so the API base can change without rebuilding.
set -e

: "${API_BASE:=}"
: "${USE_MOCKS:=false}"

case "$USE_MOCKS" in
  1 | true | TRUE | yes) USE_MOCKS_BOOL=true ;;
  *) USE_MOCKS_BOOL=false ;;
esac

cat > /usr/share/nginx/html/config.js <<EOF
window.__WT_ADMIN__ = { apiBase: "${API_BASE}", useMocks: ${USE_MOCKS_BOOL} };
EOF

echo "[wt_admin] config.js → apiBase='${API_BASE}' useMocks=${USE_MOCKS_BOOL}"
