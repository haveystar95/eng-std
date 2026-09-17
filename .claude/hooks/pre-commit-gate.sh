#!/usr/bin/env bash
# PreToolUse(Bash) gate: block `git commit` when the project's quality gates fail.
#
# Mechanical version of the standing rule "arch 0, stan L8, pest green, flutter analyze clean".
# Scope-aware: backend2 commits run `composer check` (arch+stan+test) in Docker; mobile commits
# run `flutter analyze`; a commit touching both runs both. Explicit bypass: SKIP_GATES=1 (warns).
# Dedup: the exact staged content passing once writes a marker, so an immediate re-commit of the
# same content doesn't pay for the suite twice.
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

# Only gate real commits. `git commit` is NOT a literal substring of every commit: git takes options
# before the subcommand, and `git -C <path> commit -m …` matched nothing here and walked straight past
# the gates (хвост SESSION-1b, закрыт нарядом BACK-TAILS-1 §2.4). So: find a `git` word, skip the
# options that may stand between it and the subcommand, and look at the subcommand itself.
subcommand="$(printf '%s' "$cmd" | awk '
  { for (i = 1; i <= NF; i++) if ($i == "git" || $i ~ /\/git$/) {
      j = i + 1
      while (j <= NF) {
        if ($j == "-C" || $j == "-c" || $j == "--namespace") { j += 2; continue }   # option with a value
        if ($j ~ /^-/) { j++; continue }                                            # option without one
        break
      }
      if (j <= NF) { print $j; exit }
    } }')"
[ "$subcommand" = "commit" ] || exit 0
case "$cmd" in
  *"--help"*|*"--dry-run"*) exit 0 ;;
esac

# Where that commit will run: `git -C <path>` (the last one wins, as git resolves them in order) or
# `--work-tree=<path>`, else the session's own directory. Without this the gates would be run against
# the wrong working tree — green over code that is not the code being committed.
gitdir="$(printf '%s' "$cmd" | awk '
  { for (i = 1; i < NF; i++) {
      if ($i == "-C") last = $(i + 1)
      else if ($i ~ /^--work-tree=/) { sub(/^--work-tree=/, "", $i); last = $i }
    } }
  END { print last }')"
[ -n "$gitdir" ] && cwd="$gitdir"

# Explicit, loud bypass for a deliberate WIP commit.
if [ "${SKIP_GATES:-}" = "1" ]; then
  echo "⚠️  SKIP_GATES=1 — commit gates bypassed (arch/stan/test/analyze NOT run)." >&2
  exit 0
fi

# A path that is no repository is git's own problem — it will refuse the commit itself, and there is
# nothing here to gate.
repo="$(git -C "$cwd" rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$repo" || exit 0

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
if [ -f "$marker" ] && [ "$(cat "$marker" 2>/dev/null)" = "$key" ]; then
  echo "✓ commit gates already green for this staged content — skipping re-run." >&2
  exit 0
fi

fail=0
if [ "$backend2" = 1 ]; then
  echo "▶ backend2 gates: composer check (arch + stan + test)…" >&2
  if ! (cd backend2 && docker compose exec -T app composer check) >&2; then fail=1; fi
fi
if [ "$mobile" = 1 ]; then
  echo "▶ mobile gate: flutter analyze…" >&2
  if ! (cd mobile && flutter analyze) >&2; then fail=1; fi
fi

if [ "$fail" = 1 ]; then
  echo "" >&2
  echo "✖ Commit blocked: quality gates failed (see output above)." >&2
  echo "  Fix the failure, or set SKIP_GATES=1 for a deliberate WIP commit." >&2
  exit 2
fi

echo "$key" > "$marker"
echo "✓ commit gates green." >&2
exit 0
