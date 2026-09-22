#!/usr/bin/env python3
"""FIX-3 · every canon test of the наряд under a planted defect — the BACK-TAILS-2 tool
(docs/research/back-tails-2/tools/mutate.py) pointed at a copy of the FIX-3 worktree.

Why a copy: the worktree's sidecar (`wt_fix3`) is where the gates run, so a defect is never planted in the tree that is
committed. The copy is made with rsync into a sibling folder, mounted into a sidecar container started from the same
image, and Pest runs there by `docker exec`, on a test database of its own. A planted file is restored byte for byte
after each run; the copy is removed after the whole run.

For each mutation: the named tests on the untouched copy (they must pass — a test red before the defect proves nothing),
the defect (one exact, unique fragment of one file), the same tests again (they must fail), the file restored. Serial:
the tests share one disposable database.

Usage (from the worktree's backend2):
  rsync -a --delete --exclude node_modules --exclude 'storage/*' --exclude 'docs/design' --exclude 'docs/research' \\
        --exclude public ./ /Users/yalantisdenys/fix3-mutants/backend2/
  mkdir -p /Users/yalantisdenys/fix3-mutants/backend2/storage/{app,logs,framework/cache/data,framework/sessions,framework/views,framework/testing}
  docker run -d --rm --name wt_fix3_mut --network <the stack's network> -v /Users/yalantisdenys/fix3-mutants/backend2:/wt -w /wt \\
        -e DB_HOST=db <the app image> sleep infinity
  docker exec wt_db psql -U wordtrainer -d postgres -c 'CREATE DATABASE wordtrainer_fix3mut_test'
  python3 docs/research/fix-3/tools/mutate.py docs/research/fix-3/tools/mutations.json --out docs/research/fix-3/mutations.md
  docker stop wt_fix3_mut && rm -rf /Users/yalantisdenys/fix3-mutants
"""
import argparse
import hashlib
import json
import re
import subprocess
import time

COPY = '/Users/yalantisdenys/fix3-mutants/backend2'
CONTAINER = 'wt_fix3_mut'
OUT_ROOT = '/Users/yalantisdenys/backend2-fix3/backend2'


def write_synced(path, relative, data):
    """Writes a file of the copy and waits until the container reads the same bytes: the bind mount of Docker Desktop
    lags a write by a moment, and a test run inside that moment reads half a file (a ParseError that looks like a catch,
    or a red baseline right after a restore)."""
    open(path, 'wb').write(data)
    want = hashlib.sha1(data).hexdigest()
    for _ in range(50):
        out = subprocess.run(['docker', 'exec', '-w', '/wt', CONTAINER, 'sha1sum', relative], capture_output=True, text=True).stdout
        if out.split(' ')[0] == want:
            return
        time.sleep(0.2)
    raise RuntimeError(f'the container never saw {relative}')


def run_tests(tests, db):
    outcomes = []
    for test in tests:
        cmd = ['docker', 'exec', '-w', '/wt', '-e', f'DB_DATABASE={db}', CONTAINER, 'php', 'vendor/bin/pest', test['path']]
        if test.get('filter'):
            cmd += ['--filter', test['filter']]
        out = subprocess.run(cmd, capture_output=True, text=True).stdout
        clean = re.sub(r'\x1b\[[0-9;]*m', '', out)
        summary = [line.strip() for line in clean.splitlines() if line.strip().startswith('Tests:')]
        line = summary[-1] if summary else 'no summary'
        failed = re.search(r'(\d+) failed', line)
        passed = re.search(r'(\d+) passed', line)
        reason = ''
        for row in clean.splitlines():
            if 'Failed asserting' in row or 'Exception' in row or 'Error' in row:
                reason = row.strip()[:160]
                break
        outcomes.append({
            'test': test['path'] + (f" --filter {test['filter']}" if test.get('filter') else ''),
            'summary': line,
            'failed': int(failed.group(1)) if failed else 0,
            'passed': int(passed.group(1)) if passed else 0,
            'reason': reason,
        })
    return outcomes


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('mutations')
    parser.add_argument('--db', default='wordtrainer_fix3mut_test')
    parser.add_argument('--only', default='')
    parser.add_argument('--out', default='')
    args = parser.parse_args()

    mutations = json.load(open(args.mutations, encoding='utf-8'))
    rows = []
    for m in mutations:
        if args.only and not m['name'].startswith(args.only):
            continue
        path = f"{COPY}/{m['file']}"
        original = open(path, 'rb').read()
        text = original.decode('utf-8')
        count = text.count(m['find'])
        if count != 1:
            rows.append((m, 'SKIPPED', f'find occurs {count} times', ''))
            print(f"- {m['name']}: SKIPPED ({count})", flush=True)
            continue

        baseline = run_tests(m['tests'], args.db)
        if any(o['failed'] > 0 or o['passed'] == 0 for o in baseline):
            rows.append((m, 'BASELINE RED', ' | '.join(o['summary'] for o in baseline), ''))
            print(f"- {m['name']}: BASELINE RED", flush=True)
            continue

        write_synced(path, m['file'], text.replace(m['find'], m['replace']).encode('utf-8'))
        try:
            mutated = run_tests(m['tests'], args.db)
        finally:
            write_synced(path, m['file'], original)
            assert open(path, 'rb').read() == original, f'restore failed for {path}'

        verdict = 'FAILED (caught)' if any(o['failed'] > 0 or o['passed'] == 0 for o in mutated) else 'SURVIVED'
        rows.append((m, verdict, ' | '.join(o['summary'] for o in mutated), ' | '.join(o['reason'] for o in mutated if o['reason'])))
        print(f"- {m['name']}: {verdict}", flush=True)

    for m, verdict, summary, reason in rows:
        print(f"- {m['name']}: {verdict}\n    {summary}\n    {reason}")

    if args.out:
        with open(f"{OUT_ROOT}/{args.out}", 'w', encoding='utf-8') as fh:
            fh.write('| # | правило канона | тест | дефект | под дефектом |\n|---|---|---|---|---|\n')
            for i, (m, verdict, summary, _) in enumerate(rows, 1):
                tests = '<br>'.join(f"`{t['path']}`" + (f" «{t['filter']}»" if t.get('filter') else '') for t in m['tests'])
                defect = m.get('defect', m['name'])
                fh.write(f"| {i} | {m.get('rule', '')} | {tests} | {defect} | {verdict}: {summary} |\n")


if __name__ == '__main__':
    main()
