#!/usr/bin/env python3
"""GEN-4c-2 · THE SEAM JUDGE'S SECOND QUESTION, SCORED BY ONE MEASURE — every run of the judge on the 41 recorded replies of the
canon (`runs/judge-c.json` — v1.2, `runs/judge-c2.json` — v1.3) against the one markup (`runs/judge-c-marks.json`): found right,
found wrong, missed, and the replies the runs disagree on. The calls with one reply are counted apart — the shape v1.2 fails on.

  python3 docs/research/gen-4b/tools/judge-score.py
"""
import json
import os

RUNS = os.path.join(os.path.dirname(__file__), '..', 'runs')
marks = json.load(open(os.path.join(RUNS, 'judge-c-marks.json')))['marks']
runs = {'v1.2': 'judge-c.json', 'v1.3': 'judge-c2.json'}
found = {}
for version, name in runs.items():
    data = json.load(open(os.path.join(RUNS, name)))
    found[version] = {(day, r['id']): r['id'] in data[day]['naming'] for day in data for r in data[day]['replies']}

out = {}
for version, said in found.items():
    right = sum(1 for k, v in said.items() if v and marks[k[0]][k[1]]['names'])
    wrong = sum(1 for k, v in said.items() if v and not marks[k[0]][k[1]]['names'])
    missed = sum(1 for k, v in said.items() if not v and marks[k[0]][k[1]]['names'])
    alone = [k for k in said if len(marks[k[0]]) == 1]
    out[version] = {
        'replies': len(said), 'names_by_markup': sum(1 for d in marks.values() for m in d.values() if m['names']),
        'found': right + wrong, 'found_right': right, 'found_wrong': wrong, 'missed': missed,
        'alone': {'replies': len(alone), 'found': sum(1 for k in alone if said[k]), 'names_by_markup': sum(1 for k in alone if marks[k[0]][k[1]]['names'])},
    }
    print(f"{version}: found {right + wrong} ({right} right, {wrong} wrong), missed {missed} of {out[version]['names_by_markup']};"
          f" calls of one reply — found {out[version]['alone']['found']} of {len(alone)}, by the markup {out[version]['alone']['names_by_markup']}")
differ = []
for k in sorted(found['v1.2']):
    if found['v1.2'][k] != found['v1.3'][k]:
        m = marks[k[0]][k[1]]
        differ.append({'reply': f'{k[0]} {k[1]}', 'markup': m['names'], 'v1.2': found['v1.2'][k], 'v1.3': found['v1.3'][k], 'note': m.get('note', '')})
        print(f"  differ {k[0]} {k[1]}: markup {m['names']}, v1.2 {found['v1.2'][k]}, v1.3 {found['v1.3'][k]} {m.get('note', '')}")
out['differ'] = differ
open(os.path.join(RUNS, 'judge-c2-score.json'), 'w').write(json.dumps(out, ensure_ascii=False, indent=4) + '\n')
