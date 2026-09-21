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

# Only gate real commits — and EVERY commit in the command, not the first `git` of it (наряд CONV-2, п. 13). The line
# `git add … && git commit …` walked past the gates twice in CLIENT-CONV-1a: the old reader took the first `git` word, saw
# `add`, and let the whole chain through with no warning — a silent SKIP_GATES. So: cut the command into its simple
# commands at `&&`, `||`, `;`, `|`, a newline, a subshell's brackets and a backquote; in each one find a `git` word, skip
# the options that may stand between it and the subcommand (`git -C <path> commit` is a commit — хвост SESSION-1b,
# BACK-TAILS-1 §2.4), and look at the subcommand itself. Quotes are read through: `bash -c 'git commit …'` is a commit
# too, and a false alarm (`echo "git commit"`) costs a gate run, while a missed commit costs the gates. The first commit
# found decides where the gates run — its own `-C <path>` or `--work-tree=<path>`, else the session's directory — and
# whether it asked to skip them (`SKIP_GATES=1 git commit …` on the commit itself: a session cannot set the hook's
# environment, only its own command, so the documented bypass lives on the command line as well).
found="$(printf '%s\n' "$cmd" | awk '
  {
    line = $0
    gsub(/["\047]/, " ", line)
    gsub(/&&|\|\||;|\||\(|\)|`|\$\(/, "\n", line)
    n = split(line, parts, "\n")
    for (p = 1; p <= n; p++) {
      m = split(parts[p], w, /[ \t]+/)
      for (i = 1; i <= m; i++) {
        if (w[i] != "git" && w[i] !~ /\/git$/) continue
        dir = ""
        j = i + 1
        while (j <= m) {
          if (w[j] == "") { j++; continue }
          if (w[j] == "-C") { dir = w[j + 1]; j += 2; continue }                      # the directory it runs in
          if (w[j] == "-c" || w[j] == "--namespace") { j += 2; continue }            # an option with a value
          if (w[j] ~ /^--work-tree=/) { dir = w[j]; sub(/^--work-tree=/, "", dir); j++; continue }
          if (w[j] ~ /^-/) { j++; continue }                                         # an option without one
          break
        }
        if (j <= m && w[j] == "commit") {
          rest = ""
          for (k = j + 1; k <= m; k++) rest = rest " " w[k]
          if (rest ~ /(^| )(--help|--dry-run)( |$)/) continue
          skip = 0
          for (k = 1; k < i; k++) if (w[k] == "SKIP_GATES=1") skip = 1
          print "commit\t" dir "\t" skip
          exit
        }
      }
    }
  }')"
[ -n "$found" ] || exit 0
gitdir="$(printf '%s' "$found" | cut -f2)"
asked="$(printf '%s' "$found" | cut -f3)"
[ -n "$gitdir" ] && cwd="$gitdir"

# Explicit, loud bypass for a deliberate WIP commit — set in the hook's environment, or on the commit itself.
if [ "${SKIP_GATES:-}" = "1" ] || [ "$asked" = "1" ]; then
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
