"""GEN-2a · a fresh `lesson_day.v4.4` plan on the LIVE stack (:8001 + horizon), for the current client on the simulator.

    python3 docs/research/gen-2a/tools/sim_plan.py create qa-dayui3@wt.test      # build, wait for day 1, start
    python3 docs/research/gen-2a/tools/sim_plan.py walk <plan_id> words phrases   # answer those stages through the API
    python3 docs/research/gen-2a/tools/sim_plan.py close <plan_id>                # walk the rest and close day 1

The API's throttle is real (120 a minute): answers are paced, a 429 is waited out.
"""
import json
import sys
import time
import urllib.error
import urllib.request

BASE = 'http://localhost:8001/api/v1'
STATE = __file__.rsplit('/', 1)[0] + '/../sim-plan.json'


def call(method, path, token=None, body=None):
    for _ in range(6):
        req = urllib.request.Request(BASE + path, method=method, data=None if body is None else json.dumps(body).encode(),
                                     headers={'Accept': 'application/json', 'Content-Type': 'application/json',
                                              **({'Authorization': f'Bearer {token}'} if token else {})})
        try:
            with urllib.request.urlopen(req, timeout=120) as r:
                raw = r.read()
                return r.status, (json.loads(raw) if raw else None)
        except urllib.error.HTTPError as e:
            if e.code == 429:
                time.sleep(20)
                continue
            raw = e.read()
            return e.code, (json.loads(raw) if raw else None)
    raise RuntimeError('throttled')


def token(email):
    status, body = call('POST', '/auth/dev', body={'email': email, 'device_name': 'gen-2a-sim', 'timezone': 'Europe/Kyiv'})
    assert status == 200, body
    return body['token']


def say(*parts):
    print(time.strftime('%H:%M:%S'), *parts, flush=True)


def create(email):
    t = token(email)
    goal = 'Иду к врачу с сыном: у него третий день температура и болит горло. Нужно рассказать симптомы и понять назначения'
    status, build = call('POST', '/plans', t, {'goal_text': goal, 'target_lang': 'en', 'level': 'beginner', 'days_total': 2})
    say('POST /plans', status, build['data']['status'])
    plan_id = build['data']['id']
    for _ in range(120):
        _, b = call('GET', f'/plans/{plan_id}/build', t)
        if b['data']['status'] != 'building':
            break
        time.sleep(3)
    say('build', b['data']['status'], 'cost', b['data'].get('cost_usd'))
    for _ in range(120):
        _, plan = call('GET', f'/plans/{plan_id}', t)
        if plan['data']['days'][0]['lesson_status'] in ('ready', 'failed'):
            break
        time.sleep(3)
    say('day 1 lesson', plan['data']['days'][0]['lesson_status'])
    status, started = call('POST', f'/plans/{plan_id}/start', t)
    say('start', status, started['data']['status'] if started else None)
    json.dump({'email': email, 'plan_id': plan_id}, open(STATE, 'w'))
    return plan_id


def walk(plan_id, stages, email):
    t = token(email)
    _, opened = call('POST', f'/plans/{plan_id}/days/1/open', t)
    queue = [c for c in opened['data']['cards'] if c['stage'] in stages and c['result'] is None]
    say('open day 1:', len(opened['data']['cards']), 'cards;', len(queue), 'to answer in', stages)
    while queue:
        card = queue.pop(0)
        status, out = call('POST', f"/plans/{plan_id}/days/1/cards/{card['id']}/answer", t, {'result': 'passed', 'attempts': 1})
        if status != 200:
            say('answer', card['kind'], status, out)
            continue
        if out['data']['requeued']:
            queue.append(out['data']['requeued'])
        time.sleep(0.55)
    for stage in stages:
        status, _ = call('POST', f'/plans/{plan_id}/days/1/stages/{stage}/close', t)
        say('close stage', stage, status)


if __name__ == '__main__':
    command = sys.argv[1]
    if command == 'create':
        create(sys.argv[2])
    elif command == 'walk':
        state = json.load(open(STATE))
        walk(sys.argv[2], sys.argv[3:], state['email'])
    elif command == 'close':
        state = json.load(open(STATE))
        walk(sys.argv[2], ['words', 'phrases', 'dialogue', 'listen', 'speak'], state['email'])
        status, closed = call('POST', f'/plans/{sys.argv[2]}/days/1/close', token(state['email']))
        say('close day', status, closed['data']['metrics'] if closed else None)
