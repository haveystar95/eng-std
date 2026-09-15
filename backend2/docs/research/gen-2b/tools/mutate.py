#!/usr/bin/env python3
"""Run each canon test under a defect: mutate one file, run the named test, restore the file byte for byte.

Usage (from backend2): python3 docs/research/gen-2b/tools/mutate.py docs/research/gen-2b/tools/mutations.json
"""
import json
import re
import subprocess
import sys

ROOT = '/Users/yalantisdenys/eng-std/backend2'

mutations = json.load(open(sys.argv[1], encoding='utf-8'))
results = []
for m in mutations:
    path = f"{ROOT}/{m['file']}"
    original = open(path, 'rb').read()
    text = original.decode('utf-8')
    count = text.count(m['find'])
    if count != 1:
        results.append((m['name'], f"SKIPPED: find occurs {count} times"))
        continue
    open(path, 'wb').write(text.replace(m['find'], m['replace']).encode('utf-8'))
    try:
        runs = []
        for test in m['tests']:
            cmd = ['docker', 'compose', 'exec', '-T', 'app', 'php', 'vendor/bin/pest', test['path']]
            if test.get('filter'):
                cmd += ['--filter', test['filter']]
            out = subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).stdout
            clean = re.sub(r'\x1b\[[0-9;]*m', '', out)
            summary = [line.strip() for line in clean.splitlines() if line.strip().startswith('Tests:')]
            reason = ''
            for line in clean.splitlines():
                if 'Failed asserting' in line or 'Exception' in line or 'Error' in line:
                    reason = line.strip()[:200]
                    break
            runs.append(f"{test['path']}{' --filter ' + test['filter'] if test.get('filter') else ''}: {summary[-1] if summary else 'no summary'} {reason}")
        results.append((m['name'], ' | '.join(runs)))
    finally:
        open(path, 'wb').write(original)
        assert open(path, 'rb').read() == original, f"restore failed for {path}"

for name, outcome in results:
    print(f"- {name}\n    {outcome}")
