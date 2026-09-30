#!/usr/bin/env bash
# PreToolUse(Bash) gate: block `git commit` when the project's quality gates fail.
#
# Mechanical version of the standing rule "arch 0, stan L8, pest green, flutter analyze clean".
# Scope-aware: backend2 commits run `composer check` (arch+stan+test) in Docker; mobile commits
# run `flutter analyze`; a commit touching both runs both. Explicit bypass: SKIP_GATES=1 (warns).
# Dedup: the exact staged content passing once writes a marker, so an immediate re-commit of the
# same content doesn't pay for the suite twice.
#
# THE TREE CHECKED IS THE TREE COMMITTED FROM (наряд CLIENT-22-1, п. 6). A commit from a linked git worktree used to be
# gated as the main checkout: `cd <worktree> && git commit …` fell back to the session's directory (the main tree), and
# backend2's `docker compose exec app` runs in `wt_app`, which mounts the main tree whatever tree the commit is made in —
# so sessions committed from worktrees with SKIP_GATES=1. Now the commit's directory follows every `cd` / `pushd` before
# it in the command and its own `-C` / `--work-tree`, and a linked worktree is checked as itself (see the gates below).
#
# GATES_DRY_RUN=1 in the hook's environment prints the tree and the gate commands instead of running them.
#
# Contract (see https://code.claude.com/docs/en/hooks): reads the tool call as JSON on stdin,
# blocks by exiting 2 with the reason on stderr, allows by exiting 0.

set -uo pipefail
export PATH="/opt/homebrew/bin:/usr/local/bin:$PATH"
export LANG="${LANG:-en_US.UTF-8}"

input="$(cat)"

# jq is required to read the tool call; if it's missing, don't wedge every commit — allow and warn.
if ! command -v jq >/dev/null 2>&1; then
  echo "⚠️  pre-commit-gate: jq not found; gates NOT run." >&2
  exit 0
fi

cmd="$(printf '%s' "$input" | jq -r '.tool_input.command // ""')"
cwd="$(printf '%s' "$input" | jq -r '.cwd // "."')"
dry="${GATES_DRY_RUN:-}"

# Only gate real commits — and EVERY commit in the command, not the first `git` of it (наряд CONV-2, п. 13). The line
# `git add … && git commit …` walked past the gates twice in CLIENT-CONV-1a: the old reader took the first `git` word, saw
# `add`, and let the whole chain through with no warning — a silent SKIP_GATES. So: cut the command into its simple
# commands at `&&`, `||`, `;`, `|`, a newline, a subshell's brackets and a backquote; in each one find a `git` word, skip
# the options that may stand between it and the subcommand (`git -C <path> commit` is a commit — хвост SESSION-1b,
# BACK-TAILS-1 §2.4), and look at the subcommand itself. Quotes are read through: `bash -c 'git commit …'` is a commit
# too, and a false alarm (`echo "git commit"`) costs a gate run, while a missed commit costs the gates. The first commit
# found decides where the gates run and whether it asked to skip them (`SKIP_GATES=1 git commit …` on the commit itself:
# a session cannot set the hook's environment, only its own command, so the documented bypass lives on the command line
# as well). Where it runs is read the way the shell and git read it: every `cd` / `pushd` before the commit, then its
# own `-C` options in order, then `--work-tree` — each one relative to the directory before it. Fields are joined by
# US (\037): an empty field between two tabs would be lost to `read`, and a path holds no US.
found="$(printf '%s\n' "$cmd" | awk '
  {
    line = $0
    gsub(/["\047]/, " ", line)
    gsub(/&&|\|\||;|\||\(|\)|`|\$\(/, "\n", line)
    n = split(line, parts, "\n")
    for (p = 1; p <= n; p++) {
      m = split(parts[p], w, /[ \t]+/)
      f = 1                                                                            # past `VAR=value` prefixes
      while (f <= m && (w[f] == "" || w[f] ~ /^[A-Za-z_][A-Za-z0-9_]*=/)) f++
      if (f <= m && (w[f] == "cd" || w[f] == "pushd")) {
        if (f + 1 <= m && w[f + 1] != "" && w[f + 1] != "-") cds = cds "\037" w[f + 1]
        continue
      }
      for (i = 1; i <= m; i++) {
        if (w[i] != "git" && w[i] !~ /\/git$/) continue
        dirs = ""
        tree = ""
        j = i + 1
        while (j <= m) {
          if (w[j] == "") { j++; continue }
          if (w[j] == "-C") { dirs = dirs "\037" w[j + 1]; j += 2; continue }        # the directory it runs in
          if (w[j] == "-c" || w[j] == "--namespace") { j += 2; continue }            # an option with a value
          if (w[j] ~ /^--work-tree=/) { tree = w[j]; sub(/^--work-tree=/, "", tree); j++; continue }
          if (w[j] == "--work-tree") { tree = w[j + 1]; j += 2; continue }
          if (w[j] ~ /^-/) { j++; continue }                                         # an option without one
          break
        }
        if (j <= m && w[j] == "commit") {
          rest = ""
          for (k = j + 1; k <= m; k++) rest = rest " " w[k]
          if (rest ~ /(^| )(--help|--dry-run)( |$)/) continue
          skip = 0
          for (k = 1; k < i; k++) if (w[k] == "SKIP_GATES=1") skip = 1
          print "commit\t" cds "\t" dirs "\t" tree "\t" skip
          exit
        }
      }
    }
  }')"
[ -n "$found" ] || exit 0
asked="$(printf '%s' "$found" | cut -f5)"

# Explicit, loud bypass for a deliberate WIP commit — set in the hook's environment, or on the commit itself.
if [ "${SKIP_GATES:-}" = "1" ] || [ "$asked" = "1" ]; then
  echo "⚠️  SKIP_GATES=1 — commit gates bypassed (arch/stan/test/analyze NOT run)." >&2
  exit 0
fi

# at <base> <path> — <path> as seen from <base>: absolute as it is, `~` at home, anything else under <base>.
at() {
  local p="$2"
  case "$p" in
    "~") p="$HOME" ;;
    "~/"*) p="$HOME/${p:2}" ;;
  esac
  case "$p" in
    /*) printf '%s' "$p" ;;
    *) printf '%s/%s' "$1" "$p" ;;
  esac
}

dir="$cwd"
set -f
saved_ifs="$IFS"
IFS=$'\037'
for step in $(printf '%s' "$found" | cut -f2) $(printf '%s' "$found" | cut -f3) $(printf '%s' "$found" | cut -f4); do
  [ -n "$step" ] && dir="$(at "$dir" "$step")"
done
IFS="$saved_ifs"
set +f

# A path that is no repository is git's own problem — it will refuse the commit itself, and there is
# nothing here to gate.
repo="$(git -C "$dir" rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$repo" || exit 0
repo="$(pwd -P)"

# The main checkout — the first entry of the worktree list. It owns the running stack (`wt_app` mounts its backend2),
# its `vendor/` and its `.env`; any other tree of the repository is a linked worktree.
main="$(git worktree list --porcelain 2>/dev/null | awk '/^worktree / { sub(/^worktree /, ""); print; exit }')"
if [ -n "$main" ] && [ -d "$main" ]; then main="$(cd "$main" && pwd -P)"; else main="$repo"; fi

# What this commit will touch. Staged content normally; fall back to all tracked changes so a
# `git commit -a` (which stages during the commit, after this hook runs) is still scoped correctly.
files="$(git diff --cached --name-only)"
[ -z "$files" ] && files="$(git diff HEAD --name-only 2>/dev/null || true)"
[ -z "$files" ] && exit 0 # nothing to gate (e.g. an empty or metadata-only commit)

printf '%s\n' "$files" | grep -q '^backend2/' && backend2=1 || backend2=0
printf '%s\n' "$files" | grep -q '^mobile/'   && mobile=1   || mobile=0
[ "$backend2" = 0 ] && [ "$mobile" = 0 ] && exit 0 # touches neither project → nothing to run

# Dedup: skip if this exact staged content already passed the same scope.
marker="$(git rev-parse --git-dir)/claude-gates.pass"
stamp="$(git diff --cached | shasum -a 256 | awk '{print $1}')"
key="b${backend2}m${mobile}:${stamp}"
if [ "$dry" != 1 ] && [ -f "$marker" ] && [ "$(cat "$marker" 2>/dev/null)" = "$key" ]; then
  echo "✓ commit gates already green for this staged content — skipping re-run." >&2
  exit 0
fi

# run <what> <command…> — one gate step, its output on stderr; under GATES_DRY_RUN=1 only its line.
run() {
  local what="$1"
  shift
  echo "▶ $what" >&2
  if [ "$dry" = 1 ]; then
    printf '  $' >&2
    printf ' %q' "$@" >&2
    printf '\n' >&2
    return 0
  fi
  "$@" >&2
}

if [ "$repo" = "$main" ]; then
  echo "▶ tree: $repo (the main checkout)" >&2
else
  echo "▶ tree: $repo (a linked worktree of $main)" >&2
fi

fail=0
if [ "$backend2" = 1 ]; then
  if [ "$repo" = "$main" ]; then
    # The main checkout: the running app container has exactly this tree mounted.
    (cd backend2 && run "backend2 gates: composer check (arch + stan + test) in wt_app" docker compose exec -T app composer check) || fail=1
  else
    # A LINKED WORKTREE: the main stack's image in a one-off container with THIS tree at /wt, on a test database of
    # its own — `wordtrainer_<worktree>_test`, and paratest's `…_test_test_N` beside it — so a run here neither reads
    # the main tree nor drops the databases of a main-tree run going at the same time. The worktree has no `vendor/`
    # or `.env` of its own until the first gate run gives it the main checkout's (an APFS clone, then kept); the
    # database is created when missing and migrated before the suite (the Feature files without RefreshDatabase read
    # it as it is). The step refuses a database whose name does not end in `_test`.
    wt="$repo/backend2"
    mb="$main/backend2"
    slug="$(printf '%s' "$(basename "$repo")" | tr '[:upper:]' '[:lower:]' | tr -c 'a-z0-9' '_' | cut -c1-30)"
    db="wordtrainer_${slug}_test"
    compose=(docker compose --project-directory "$mb" -f "$mb/docker-compose.yml")
    [ -d "$wt/vendor" ] || run "backend2: the worktree has no vendor/ — cloning the main checkout's" cp -Rc "$mb/vendor" "$wt/vendor" || fail=1
    [ -f "$wt/.env" ] || run "backend2: the worktree has no .env — copying the main checkout's" cp "$mb/.env" "$wt/.env" || fail=1
    if [ "$dry" = 1 ]; then
      echo "▶ backend2: test database $db (created when missing)" >&2
    elif [ "$("${compose[@]}" exec -T db psql -U wordtrainer -d wordtrainer -tAc "SELECT 1 FROM pg_database WHERE datname = '$db'" 2>/dev/null)" != 1 ]; then
      run "backend2: test database $db" "${compose[@]}" exec -T db psql -U wordtrainer -d wordtrainer -qc "CREATE DATABASE $db" || fail=1
    fi
    if [ "$fail" = 0 ]; then
      # shellcheck disable=SC2016 # expanded by the container's shell, not this one
      run "backend2 gates: composer check (arch + stan + test) on $wt, database $db" \
        "${compose[@]}" run --rm --no-deps -T -v "$wt:/wt" -w /wt -e DB_DATABASE="$db" app sh -c \
        'case "$DB_DATABASE" in *_test) ;; *) echo "refused: $DB_DATABASE is no test database" >&2; exit 3 ;; esac
         php artisan config:clear -q && php artisan migrate --force -q && composer check' || fail=1
    fi
  fi
fi
if [ "$mobile" = 1 ]; then
  (cd mobile && run "mobile gate: flutter analyze in $repo/mobile" flutter analyze) || fail=1
fi

if [ "$fail" = 1 ]; then
  echo "" >&2
  echo "✖ Commit blocked: quality gates failed (see output above)." >&2
  echo "  Fix the failure, or set SKIP_GATES=1 for a deliberate WIP commit." >&2
  exit 2
fi

[ "$dry" = 1 ] && exit 0
echo "$key" > "$marker"
echo "✓ commit gates green." >&2
exit 0
