#!/usr/bin/env python3
"""
ACC-1 — the stand before and after the deploy, as the phone reads it (`prod-smoke.php`), compared field by field.

The only differences allowed are what the order adds: `lock_reason` on every day (`Plan.days[]`, `current_day`, the room's
`day`) and `access` on `/auth/me`. Everything else — statuses, stages, slots, windows, cards, the talk rows — must be the
same, byte for byte, at the same frozen moment. Prints no personal data: plan ids, day numbers, field paths and the
values of the two new fields only.

Usage: python3 compare-smoke.py <before.json> <after.json>
"""
import json
import sys

ADDED_KEYS = {'lock_reason', 'access'}


def strip(node, path, found):
    if isinstance(node, dict):
        out = {}
        for k, v in node.items():
            if k in ADDED_KEYS:
                found.append((path + '.' + k, v))
                continue
            out[k] = strip(v, path + '.' + k, found)
        return out
    if isinstance(node, list):
        return [strip(v, f'{path}[{i}]', found) for i, v in enumerate(node)]
    return node


def diff(a, b, path, out):
    if type(a) is not type(b):
        out.append(path)
        return
    if isinstance(a, dict):
        for k in sorted(set(a) | set(b)):
            if k not in a or k not in b:
                out.append(f'{path}.{k}')
            else:
                diff(a[k], b[k], f'{path}.{k}', out)
    elif isinstance(a, list):
        if len(a) != len(b):
            out.append(f'{path} (length {len(a)} → {len(b)})')
        for i, (x, y) in enumerate(zip(a, b)):
            diff(x, y, f'{path}[{i}]', out)
    elif a != b:
        out.append(path)


before = json.load(open(sys.argv[1]))
after = json.load(open(sys.argv[2]))
added_before, added_after = [], []
b = strip(before, '', added_before)
a = strip(after, '', added_after)

print(f"frozen at {before['frozen_at']} / {after['frozen_at']}; db {before['db']} / {after['db']}")
print(f"plans {len(before['plans'])} / {len(after['plans'])}; accounts {len(before['me'])} / {len(after['me'])}")
print(f"fields of ACC-1 before the deploy: {len(added_before)}")
differences = []
diff(b, a, '', differences)
print(f"differences outside lock_reason / access: {len(differences)}")
for d in differences[:50]:
    print('   ' + d)

print('\nlock_reason after, by plan and day:')
for pid, entry in after['plans'].items():
    days = entry['plan']['days']
    print(f"   {pid} ({entry['plan']['status']}): " + ', '.join(f"{d['number']} {d['status']} → {d['lock_reason']}" for d in days))
    if 'room' in entry:
        rd = entry['room']['day']
        print(f"      room of day {rd['number']}: {rd['status']} → {rd['lock_reason']}; window rows: {[s['stage'] for s in entry['room']['window']['stages']]}")
plans = {}
for uid, me in after['me'].items():
    access = me.get('access')
    key = json.dumps(access, sort_keys=True)
    plans[key] = plans.get(key, 0) + 1
print('\naccess after, by value (accounts):')
for key, n in sorted(plans.items()):
    print(f"   {n} × {key}")
sys.exit(0 if not differences else 1)
