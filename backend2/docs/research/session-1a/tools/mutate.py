#!/usr/bin/env python3
"""Every canon test under a planted defect (наряд SESSION-1a, разд. 8; the GEN-2b tool with a baseline and a verdict).

For each mutation: run the named tests on the untouched code (they must pass — a test that is red before the defect
proves nothing), plant the defect (one exact, unique fragment of one file), run the same tests again (they must fail),
restore the file byte for byte. Serial by design: the tests share one disposable database.

Usage (from backend2):
  python3 docs/research/session-1a/tools/mutate.py docs/research/session-1a/tools/mutations.json [--db wordtrainer_test_test_10] [--only <name prefix>] [--out docs/research/session-1a/mutations.md]

mutations.json: [{"name": "...", "rule": "правило канона", "file": "app/...", "find": "...", "replace": "...",
                  "tests": [{"path": "tests/...", "filter": "plain words of the test name"}]}]
"""
import argparse
import json
import re
import subprocess

ROOT = '/Users/yalantisdenys/eng-std/backend2'


def run_tests(tests, db):
    outcomes = []
    for test in tests:
        cmd = ['docker', 'compose', 'exec', '-T', '-e', f'DB_DATABASE={db}', 'app', 'php', 'vendor/bin/pest', test['path']]
        if test.get('filter'):
            cmd += ['--filter', test['filter']]
        out = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).stdout
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
    parser.add_argument('--db', default='wordtrainer_test_test_10')
    parser.add_argument('--only', default='')
    parser.add_argument('--out', default='')
    args = parser.parse_args()

    mutations = json.load(open(args.mutations, encoding='utf-8'))
    rows = []
    for m in mutations:
        if args.only and not m['name'].startswith(args.only):
            continue
        path = f"{ROOT}/{m['file']}"
        original = open(path, 'rb').read()
        text = original.decode('utf-8')
        count = text.count(m['find'])
        if count != 1:
            rows.append((m, 'SKIPPED', f'find occurs {count} times', ''))
            continue

        baseline = run_tests(m['tests'], args.db)
        if any(o['failed'] > 0 or o['passed'] == 0 for o in baseline):
            rows.append((m, 'BASELINE RED', ' | '.join(o['summary'] for o in baseline), ''))
            continue

        open(path, 'wb').write(text.replace(m['find'], m['replace']).encode('utf-8'))
        try:
            mutated = run_tests(m['tests'], args.db)
        finally:
            open(path, 'wb').write(original)
            assert open(path, 'rb').read() == original, f'restore failed for {path}'

        verdict = 'FAILED (caught)' if any(o['failed'] > 0 or o['passed'] == 0 for o in mutated) else 'SURVIVED'
        rows.append((m, verdict, ' | '.join(o['summary'] for o in mutated), ' | '.join(o['reason'] for o in mutated if o['reason'])))

    for m, verdict, summary, reason in rows:
        print(f"- {m['name']}: {verdict}\n    {summary}\n    {reason}")

    if args.out:
        with open(f"{ROOT}/{args.out}", 'w', encoding='utf-8') as fh:
            fh.write('| # | правило канона | тест | дефект | под дефектом |\n|---|---|---|---|---|\n')
            for i, (m, verdict, summary, _) in enumerate(rows, 1):
                tests = '<br>'.join(f"`{t['path']}`" + (f" «{t['filter']}»" if t.get('filter') else '') for t in m['tests'])
                defect = m.get('defect', m['name'])
                fh.write(f"| {i} | {m.get('rule', '')} | {tests} | {defect} | {verdict}: {summary} |\n")


if __name__ == '__main__':
    main()
