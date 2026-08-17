#!/usr/bin/env bash
#
# Dump the dev Postgres database to a local, gitignored folder.
#
#   backend2/scripts/db-backup.sh              # dumps $DB_DATABASE (default: wordtrainer)
#   DB=wordtrainer_test backend2/scripts/db-backup.sh
#
# Run this BEFORE any operation that touches the database — a migration on the dev database, a
# seeder, a content backfill, a manual UPDATE. On 2026-08-14 a bare `migrate:fresh` dropped
# `wordtrainer` and there was nothing to restore from; this script is the missing half of that
# lesson (the other half is the guard in AppServiceProvider).
#
# Restore the newest dump:
#   gunzip -c backend2/storage/db-backups/<file>.sql.gz \
#     | docker compose -f backend2/docker-compose.yml exec -T db psql -U wordtrainer -d wordtrainer
#
set -euo pipefail

cd "$(dirname "$0")/.."

DB="${DB:-${DB_DATABASE:-wordtrainer}}"
USER="${DB_USERNAME:-wordtrainer}"
DIR="storage/db-backups"
KEEP="${KEEP:-20}"

mkdir -p "$DIR"
OUT="$DIR/${DB}-$(date +%Y%m%d-%H%M%S).sql.gz"

# --no-owner/--no-privileges so the dump restores into a fresh container without role juggling.
docker compose exec -T db pg_dump -U "$USER" --no-owner --no-privileges "$DB" | gzip >"$OUT"

# A dump of a dropped database is a valid, tiny, useless file — say the size out loud.
echo "backup: $OUT ($(du -h "$OUT" | cut -f1))"

# Keep the last $KEEP dumps of this database, drop the rest.
ls -1t "$DIR/${DB}-"*.sql.gz 2>/dev/null | tail -n "+$((KEEP + 1))" | while read -r old; do
	echo "pruned: $old"
	rm -f "$old"
done
