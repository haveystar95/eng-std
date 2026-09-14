#!/usr/bin/env python3
"""DAY-UI-3 fixtures: a fresh QA plan walked through the day window's three states over the live API.

Usage: snap_window.py <email> <out_dir> [goal]

The DAY-UI-2 walk (`../day-ui-2/tools/snap_window.py`) with the DAY-UI-3 checks on the way: day 1 is
`ready` only with its pictures on it — every word of the window has a photo the moment the lesson is
ready, before anything is opened — and the time from «plan ready» to «day 1 ready» is printed (the
lesson call plus the parallel photo batches). The voice is not waited for: the day does not wait for it.

Writes room_window_not_started.json, room_window_in_progress.json, room_window_passed.json,
cards_window_passed.json, plan_window.json.
"""
import json
import sys
import time
import urllib.error
import urllib.request

BASE = 'http://localhost:8001/api/v1'
email = sys.argv[1]
out = sys.argv[2]
goal = sys.argv[3] if len(sys.argv) > 3 else 'Иду к врачу: болит спина, нужно описать боль и понять назначения'
token = None


def call(method, path, body=None, retries=6):
    data = None if body is None else json.dumps(body).encode()
    req = urllib.request.Request(BASE + path, data=data, method=method)
    req.add_header('Accept', 'application/json')
    req.add_header('Content-Type', 'application/json')
    if token:
        req.add_header('Authorization', 'Bearer ' + token)
    for attempt in range(retries):
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                raw = r.read()
                return json.loads(raw) if raw else None
        except urllib.error.HTTPError as e:
            if e.code == 429 and attempt < retries - 1:
                time.sleep(5)
                continue
            print('HTTP', e.code, method, path, e.read()[:400])
            raise


def save(name, payload):
    with open(f'{out}/{name}', 'w') as f:
        json.dump(payload, f, ensure_ascii=False, indent=1)
        f.write('\n')
    print('saved', name)


login = call('POST', '/auth/dev', {'email': email, 'device_name': 'day-ui-3', 'timezone': 'Europe/Kyiv'})
token = (login.get('data') or login).get('token')
print('token', bool(token))
call('PUT', '/profile', {'native_language': 'ru', 'target_language': 'en', 'timezone': 'Europe/Kyiv'})

current = call('GET', '/plans/current')['data']
if current is not None and current['status'] in ('active', 'overdue', 'ready'):
    print('existing plan', current['id'], current['status'], '— deleting')
    call('DELETE', f"/plans/{current['id']}")

started_at = time.time()
build = call('POST', '/plans', {'goal_text': goal, 'target_lang': 'en', 'level': 'beginner', 'days_total': 3})['data']
pid = build['id']
while build['status'] == 'building':
    time.sleep(2)
    build = call('GET', f'/plans/{pid}/build')['data']
plan_ready_at = time.time()
print('build', build['status'], build.get('cost_usd'), f'{plan_ready_at - started_at:.1f} s')
if build['status'] != 'ready':
    sys.exit(1)

plan = call('GET', f'/plans/{pid}')['data']
while plan['days'][0]['lesson_status'] != 'ready':
    time.sleep(1)
    plan = call('GET', f'/plans/{pid}')['data']
day_ready_at = time.time()
print(f'day 1 ready {day_ready_at - plan_ready_at:.1f} s after the plan (lesson + photos)')

room = call('GET', f'/plans/{pid}/days/1')['data']
words = room['window']['program']['words']['items']
missing = [w['term'] for w in words if w['image'] is None]
print('words', len(words), 'without a photo at ready:', missing)
save('room_window_not_started.json', room)

started = call('POST', f'/plans/{pid}/start')['data']
print('started', started['status'])


def cards():
    return call('POST', f'/plans/{pid}/days/1/open')['data']['cards']


def walk(stage, fail_ref=None, limit=None):
    queue = [c for c in cards() if c['stage'] == stage and c['result'] is None]
    answered = 0
    while queue:
        if limit is not None and answered >= limit:
            return
        c = queue.pop(0)
        fail = fail_ref is not None and c['unit_ref'] in fail_ref and c['kind'] == 'word_choose'
        res = call('POST', f"/plans/{pid}/days/1/cards/{c['id']}/answer", {'result': 'failed' if fail else 'passed', 'attempts': 2 if fail else 1})['data']
        answered += 1
        if res.get('requeued'):
            queue.append(res['requeued'])
        time.sleep(0.55)


def room_now():
    return call('GET', f'/plans/{pid}/days/1')['data']


walk('words', fail_ref={'v3', 'v7'})
call('POST', f'/plans/{pid}/days/1/stages/words/close')
walk('phrases')
call('POST', f'/plans/{pid}/days/1/stages/phrases/close')
walk('dialogue')
call('POST', f'/plans/{pid}/days/1/stages/dialogue/close')
walk('listen', limit=6)
save('room_window_in_progress.json', room_now())
walk('listen')
call('POST', f'/plans/{pid}/days/1/stages/listen/close')
walk('speak')
call('POST', f'/plans/{pid}/days/1/stages/speak/close')
call('POST', f'/plans/{pid}/days/1/close')
save('room_window_passed.json', room_now())
save('cards_window_passed.json', call('GET', f'/plans/{pid}/days/1/cards')['data'])
save('plan_window.json', call('GET', f'/plans/{pid}')['data'])
print('plan', pid)
