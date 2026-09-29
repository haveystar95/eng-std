#!/usr/bin/env python3
"""GEN-4b · THE E2E DAY THROUGH THE API (наряд GEN-4b §6): the plan «Собеседование в пятницу, боюсь вопросов про опыт», ru→ro,
Beginner, two days, built from nothing as the phone builds it — a QA learner signs in (`POST /auth/dev`), says their language
and gender (`PATCH /profile`), asks for the plan (`POST /plans`) — and day 1 read until its lesson is `ready` (or `failed`).

The server is the branch's code on the e2e database, the queue `sync` (the plan and day 1 are written inside `POST /plans`),
the voice off:

  docker exec -d -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e PLAN_MODEL_DRIVER=openai \
      -e SPEECH_ENABLED=false -e DEV_LOGIN_ENABLED=true -w /wt/public wt_gen4 \
      php -d max_execution_time=0 -S 0.0.0.0:8020 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
  python3 docs/research/gen-4b/tools/e2e-api.py http://localhost:8020 qa-gen4b-ru-ro-0929@wt.test

Prints every step with its status and time, and the plan's id last.
"""
import json
import sys
import time
import urllib.request

BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://localhost:8020'
EMAIL = sys.argv[2] if len(sys.argv) > 2 else 'qa-gen4b-ru-ro-0929@wt.test'
GOAL = 'Собеседование в пятницу, боюсь вопросов про опыт'


def call(method, path, body=None, token=None, timeout=900):
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
    print(f'{method} {path} → {status} in {seconds:.1f} s', file=sys.stderr)
    return status, payload


status, auth = call('POST', '/auth/dev', {'email': EMAIL, 'device_name': 'gen4b-e2e', 'timezone': 'Europe/Chisinau'})
assert status == 200, auth
token = auth['data']['token'] if 'data' in auth else auth['token']
status, profile = call('PATCH', '/profile', {'native_language': 'ru', 'gender': 'male', 'timezone': 'Europe/Chisinau'}, token)
assert status == 200, profile
status, created = call('POST', '/plans', {'goal_text': GOAL, 'target_lang': 'ro', 'level': 'beginner', 'days_total': 2}, token)
assert status in (200, 202), created
plan_id = created['data']['id']
day1 = None
for _ in range(60):
    status, plan = call('GET', f'/plans/{plan_id}', None, token)
    days = plan['data'].get('days', [])
    day1 = next((d for d in days if d.get('number') == 1), None)
    state = (day1 or {}).get('lesson_status')
    print(f"plan {plan['data'].get('status')} · day 1 lesson_status {state}", file=sys.stderr)
    if state in ('ready', 'failed'):
        break
    time.sleep(5)
print(json.dumps({'plan_id': plan_id, 'plan_status': plan['data'].get('status'), 'day1': day1}, ensure_ascii=False, indent=2))
