#!/usr/bin/env bash
#
# Dump the dev Postgres database to a local, gitignored folder.
#
#   backend2/scripts/db-backup.sh              # dumps $DB_DATABASE (default: wordtrainer)
#   backend2/scripts/db-backup.sh --safety     # the same, and prunes NOTHING
#   DB=wordtrainer_test backend2/scripts/db-backup.sh
#
# Run this BEFORE any operation that touches the database — a migration on the dev database, a
# seeder, a content backfill, a manual UPDATE. On 2026-08-14 a bare `migrate:fresh` dropped
# `wordtrainer` and there was nothing to restore from; this script is the missing half of that
# lesson (the other half is the guard in AppServiceProvider).
#
# Old dumps are kept BY AGE, not by count (наряд BACK-TAILS-1 §3.5): $DAYS days back, 30 by default.
# A count kept «the last 20» — and twenty dumps taken in one busy afternoon threw away every copy
# older than that afternoon, which is exactly the copy a bad migration needs.
#
# --safety (or SAFETY=1) prunes nothing at all. That is the flag for the run made as INSURANCE right
# before a risky operation: the one moment you must not let a backup delete an older backup, because
# if what follows corrupts the data, the fresh dump is a copy of the damage and an old one is the
# only way back.
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
DAYS="${DAYS:-30}"
SAFETY="${SAFETY:-0}"
case "${1:-}" in
	--safety|--before-migration) SAFETY=1 ;;
	'') : ;;
	*) echo "unknown argument: $1 (only --safety)" >&2; exit 2 ;;
esac

mkdir -p "$DIR"
OUT="$DIR/${DB}-$(date +%Y%m%d-%H%M%S).sql.gz"

# --no-owner/--no-privileges so the dump restores into a fresh container without role juggling.
docker compose exec -T db pg_dump -U "$USER" --no-owner --no-privileges "$DB" | gzip >"$OUT"

# A dump of a dropped database is a valid, tiny, useless file — say the size out loud.
echo "backup: $OUT ($(du -h "$OUT" | cut -f1))"

if [ "$SAFETY" = 1 ]; then
	echo "safety copy: nothing pruned"
	exit 0
fi

# Keep every dump of this database younger than $DAYS days, drop the rest — but never the newest one,
# however old: a database nobody has dumped for a month must not end up with no copy at all.
newest="$(ls -1t "$DIR/${DB}-"*.sql.gz 2>/dev/null | head -n 1 || true)"
find "$DIR" -name "${DB}-*.sql.gz" -type f -mtime "+$DAYS" -print | while read -r old; do
	[ "$old" = "$newest" ] && continue
	echo "pruned: $old (older than $DAYS days)"
	rm -f "$old"
done
