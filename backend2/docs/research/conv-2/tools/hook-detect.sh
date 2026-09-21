#!/usr/bin/env bash
# CONV-2 · WHICH COMMANDS THE GATE HOOK TAKES FOR A COMMIT (report §1.13) — the same commands through any version of
# `.claude/hooks/pre-commit-gate.sh`, nothing committed and no gate run.
#
#   bash docs/research/conv-2/tools/hook-detect.sh <path to the hook>
#
# Two readings per command, both harmless:
#   «видит»  — the hook is run with SKIP_GATES=1 in its environment: it warns exactly when it took the command for a
#              commit, and exits before any gate;
#   «обход»  — the hook is run WITHOUT it, in a directory that is no repository: a commit it cannot bypass ends at
#              «no repository» silently, so a warning here means it honoured `SKIP_GATES=1` written on the commit itself.
set -uo pipefail

hook="${1:?путь к хуку}"
nowhere="$(mktemp -d)"

commands=(
  'git commit -m "x"'
  'git add backend2/app && git commit -m "x"'
  'git add -A; git commit -m "x"'
  '(cd backend2 && git add . && git commit -m "x")'
  'git stash && git pull --rebase && git commit -am "x"'
  'git add x | tee log && git commit -m "y"'
  'bash -c '"'"'git commit -m x'"'"''
  'git -C /tmp/wt commit -m "x"'
  'git -c user.name=x commit -m "x"'
  'git --work-tree=/tmp/wt commit -m "x"'
  'cd backend2 && git commit -m "x"'
  'SKIP_GATES=1 git commit -m "wip"'
  'git add x && SKIP_GATES=1 git commit -m "wip"'
  'git status && git log --oneline -3'
  'git commit --dry-run'
  'git log --grep=commit'
  'echo done | cat'
)

printf '%-6s %-6s %s\n' 'видит' 'обход' 'команда'
for command in "${commands[@]}"; do
  json="$(jq -n --arg c "$command" --arg d "$nowhere" '{tool_input: {command: $c}, cwd: $d}')"
  seen="$(printf '%s' "$json" | SKIP_GATES=1 bash "$hook" 2>&1 >/dev/null | grep -c 'SKIP_GATES=1')"
  asked="$(printf '%s' "$json" | env -u SKIP_GATES bash "$hook" 2>&1 >/dev/null | grep -c 'SKIP_GATES=1')"
  printf '%-6s %-6s %s\n' "$([ "$seen" -gt 0 ] && echo да || echo —)" "$([ "$asked" -gt 0 ] && echo да || echo —)" "$command"
done

rmdir "$nowhere"
