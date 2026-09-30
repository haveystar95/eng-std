#!/usr/bin/env python3
"""GEN-4c-2 · A DAY WALKED AND CLOSED THROUGH THE API, AS THE PHONE DOES IT — so that the next day's lesson is built the way
production builds it: closing day N asks for the lesson of day N+1 (`CloseDayHandler`, наряд GEN-3 §11), and a day closes only
when every card is answered and the talk (the sixth stage) is walked.

The QA learner signs in again (`POST /auth/dev`, the same e-mail — the same learner), the plan is started if it is not
(`POST /plans/{id}/start`), the day opened (`POST …/days/{n}/open`), every card answered as the phone's walk answers it
(`speak_answer`, the one card whose pass is the judge's, given up — `skipped` — like the test suite's walk; every other card
`passed`; a card dealt again is answered too), every stage closed, the talk walked — the learner says the day's own lines, in
the order of the day's dialogue, until the role says goodbye — and the day closed (`POST …/days/{n}/close`): the day's summary,
and inside the same request, the queue being `sync`, the build of the next day. Then the next day is read until its lesson is
`ready` or `failed`.

  python3 docs/research/gen-4b/tools/e2e-walk.py http://localhost:8030 qa-gen4c-ru-ro-3-0929@wt.test 01M3QET095394QGAWYFS7K1JR6 1

Prints every step with its status and time, and the next day last.
"""
import json
import sys
import time
import urllib.request

BASE, EMAIL, PLAN, DAY = sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4])
STAGES = ['words', 'phrases', 'dialogue', 'listen', 'speak', 'recall', 'repetition']


def call(method, path, body=None, token=None, timeout=900, quiet=False):
    data = None if body is None else json.dumps(body).encode()
    request = urllib.request.Request(BASE + '/api/v1' + path, data=data, method=method)
    request.add_header('Accept', 'application/json')
    if data is not None:
        request.add_header('Content-Type', 'application/json')
    if token:
        request.add_header('Authorization', 'Bearer ' + token)
    started = time.time()
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            status, raw = response.status, response.read()
    except urllib.error.HTTPError as e:
        status, raw = e.code, e.read()
    seconds = time.time() - started
    payload = json.loads(raw) if raw else None
    if not quiet or status >= 300:
        print(f'{method} {path} → {status} in {seconds:.1f} s', file=sys.stderr)
    if status >= 300 and not quiet:
        print(json.dumps(payload, ensure_ascii=False)[:600], file=sys.stderr)
    return status, payload


status, auth = call('POST', '/auth/dev', {'email': EMAIL, 'device_name': 'gen4c-walk', 'timezone': 'Europe/Chisinau'})
assert status == 200, auth
token = auth['data']['token'] if 'data' in auth else auth['token']

status, plan = call('GET', f'/plans/{PLAN}', None, token)
print(f"plan {plan['data'].get('status')}", file=sys.stderr)
if plan['data'].get('status') == 'ready':
    status, started = call('POST', f'/plans/{PLAN}/start', {}, token)
    assert status == 200, started

status, opened = call('POST', f'/plans/{PLAN}/days/{DAY}/open', {}, token)
assert status == 200, opened
cards = opened['data']['cards']
queue, seen, answered = list(cards), set(), 0
while queue:
    card = queue.pop(0)
    if card['id'] in seen or card.get('result') is not None:
        continue
    seen.add(card['id'])
    result = 'skipped' if card['kind'] == 'speak_answer' else 'passed'
    status, outcome = call('POST', f"/plans/{PLAN}/days/{DAY}/cards/{card['id']}/answer", {'result': result, 'attempts': 1}, token, quiet=True)
    assert status == 200, outcome
    answered += 1
    if outcome['data'].get('requeued'):
        queue.append(outcome['data']['requeued'])
print(f'answered {answered} cards of {len(cards)} dealt', file=sys.stderr)
for stage in STAGES:
    call('POST', f'/plans/{PLAN}/days/{DAY}/stages/{stage}/close', {}, token, quiet=True)

# The learner's own lines of the day, in the order of its dialogue — what a learner who learned the day says in the talk.
status, room = call('GET', f'/plans/{PLAN}/days/{DAY}', None, token, quiet=True)
lines = []
for c in cards:
    own = (c.get('payload') or {}).get('own_line') or {}
    if own.get('text_target') and own['text_target'] not in lines:
        lines.append(own['text_target'])
if not lines:
    lines = ['Yes.']
status, talk = call('POST', f'/plans/{PLAN}/days/{DAY}/conversation', {'again': False}, token)
turns = 0
if status == 200:
    talk = talk['data']
    while talk['state'] != 'ended' and turns < 40:
        heard = lines[turns % len(lines)]
        status, moved = call('POST', f"/plans/{PLAN}/conversation/{talk['id']}/turn", {'kind': 'said', 'heard': heard}, token, quiet=True)
        assert status == 200, moved
        talk = moved['data']
        turns += 1
    print(f"talk {talk['id']} ended after {turns} moves of the learner", file=sys.stderr)
else:
    print('no talk for this day', file=sys.stderr)

started = time.time()
status, closed = call('POST', f'/plans/{PLAN}/days/{DAY}/close', {}, token)
assert status == 200, closed
print(f'day {DAY} closed in {time.time() - started:.0f} s (the next lesson built inside the request)', file=sys.stderr)
nxt = None
for _ in range(60):
    status, plan = call('GET', f'/plans/{PLAN}', None, token, quiet=True)
    nxt = next((d for d in plan['data'].get('days', []) if d.get('number') == DAY + 1), None)
    state = (nxt or {}).get('lesson_status')
    if state in ('ready', 'failed'):
        break
    time.sleep(5)
print(json.dumps({'plan_id': PLAN, 'closed': closed.get('data'), 'next_day': nxt}, ensure_ascii=False, indent=2))
