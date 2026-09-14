#!/usr/bin/env python3
"""DAY-UI-2 fixtures: a fresh QA plan walked through the day window's three states over the live API.

Usage: snap_window.py <email> <out_dir> [goal]
Writes room_window_not_started.json, room_window_in_progress.json, room_window_passed.json,
cards_window_passed.json, plan_window.json.
"""
import json
import sys
import time
import urllib.request
import urllib.error

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


login = call('POST', '/auth/dev', {'email': email, 'device_name': 'day-ui-2', 'timezone': 'Europe/Kyiv'})
token = (login.get('data') or login).get('token')
print('token', bool(token))
call('PUT', '/profile', {'native_language': 'ru', 'target_language': 'en', 'timezone': 'Europe/Kyiv'})

current = call('GET', '/plans/current')['data']
if current is not None and current['status'] in ('active', 'overdue', 'ready'):
    print('existing plan', current['id'], current['status'], '— deleting')
    call('DELETE', f"/plans/{current['id']}")

build = call('POST', '/plans', {'goal_text': goal, 'target_lang': 'en', 'level': 'beginner', 'days_total': 3})['data']
pid = build['id']
while build['status'] == 'building':
    time.sleep(3)
    build = call('GET', f'/plans/{pid}/build')['data']
print('build', build['status'], build.get('cost_usd'))
if build['status'] != 'ready':
    sys.exit(1)

plan = call('GET', f'/plans/{pid}')['data']
while plan['days'][0]['lesson_status'] != 'ready':
    time.sleep(4)
    plan = call('GET', f'/plans/{pid}')['data']
started = call('POST', f'/plans/{pid}/start')['data']
print('started', started['status'])


def room():
    return call('GET', f'/plans/{pid}/days/1')['data']


# The photo ladder and the voice of day one run in the queue: wait until every word has its photo or
# its tone mark, every phrase and partner line its voice — a fixture with half the photos would pin
# a moment of the queue, not the contract.
for _ in range(40):
    w = room()['window']['program']
    photos = all(x['image'] is not None for x in w['words']['items'])
    voices = all(x['audio_url'] for x in w['phrases']['items']) and all((d['partner'] or {}).get('audio_url') for d in w['dialogue']['items'] if d['partner'])
    if photos and voices:
        break
    time.sleep(5)
print('photos', photos, 'voices', voices)
save('room_window_not_started.json', room())


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


walk('words', fail_ref={'v3', 'v7'})
call('POST', f'/plans/{pid}/days/1/stages/words/close')
walk('phrases')
call('POST', f'/plans/{pid}/days/1/stages/phrases/close')
walk('dialogue')
call('POST', f'/plans/{pid}/days/1/stages/dialogue/close')
walk('listen', limit=6)
save('room_window_in_progress.json', room())
walk('listen')
call('POST', f'/plans/{pid}/days/1/stages/listen/close')
walk('speak')
call('POST', f'/plans/{pid}/days/1/stages/speak/close')
call('POST', f'/plans/{pid}/days/1/close')
save('room_window_passed.json', room())
save('cards_window_passed.json', call('GET', f'/plans/{pid}/days/1/cards')['data'])
save('plan_window.json', call('GET', f'/plans/{pid}')['data'])
print('plan', pid)
