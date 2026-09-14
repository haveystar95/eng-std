#!/usr/bin/env python3
"""DAY-UI-3 live run: a fresh QA plan on the live stand (:8001 + horizon), the server's side of the simulator walk.

  live_run.py create  <email> <out_dir> [goal]   a new plan; day 1 ready with its photos; timings, photos, voice
  live_run.py window  <email> <plan_id>          what the window of day 1 carries now: photos and voice by kind
  live_run.py words   <email> <plan_id>          walk the «Слова» stage of day 1 (the plan must be started) and close it
  live_run.py voice   <email> <plan_id> [min]    wait until every line, phrase and word of day 1 has its voice

The app on the simulator logs in with the same QA email (`DEV_LOGIN_EMAIL`), so the plan made here is the plan it
shows. «Начать» is tapped in the app (it starts the plan); the answers of a stage are posted here — a simulator has
no microphone, and fifty taps would test the driver, not the window.
"""
import json
import sys
import time
import urllib.error
import urllib.request

BASE = 'http://localhost:8001/api/v1'
token = None


def call(method, path, body=None, retries=8):
    data = None if body is None else json.dumps(body).encode()
    for attempt in range(retries):
        req = urllib.request.Request(BASE + path, data=data, method=method)
        req.add_header('Accept', 'application/json')
        req.add_header('Content-Type', 'application/json')
        if token:
            req.add_header('Authorization', 'Bearer ' + token)
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


def login(email):
    global token
    res = call('POST', '/auth/dev', {'email': email, 'device_name': 'day-ui-3-live', 'timezone': 'Europe/Kyiv'})
    token = (res.get('data') or res).get('token')
    call('PUT', '/profile', {'native_language': 'ru', 'target_language': 'en', 'timezone': 'Europe/Kyiv'})


def window(pid):
    return call('GET', f'/plans/{pid}/days/1')['data']['window']


def voice_counts(w):
    p = w['program']
    lines = p['dialogue']['items']
    kinds = {
        'partner lines': [x['partner'] for x in lines if x.get('partner')],
        'learner lines': [x['learner'] for x in lines if x.get('learner')],
        'phrases': p['phrases']['items'],
        'words': p['words']['items'],
        'usage lines': [x['usage'] for x in p['words']['items'] if x.get('usage')],
    }
    return {k: (sum(1 for x in v if x.get('audio_url')), len(v)) for k, v in kinds.items()}


def describe(w):
    words = w['program']['words']['items']
    photos = sum(1 for x in words if x.get('image'))
    urls = [x['image']['url'] for x in words if x.get('image')]
    plate = (w['day'].get('image') or {}).get('url')
    print(f"day {w['day']['index']} «{w['day']['title_native']}» {w['day']['status']}, action {w.get('allowed_action')}")
    print(f"  photos: plate {'yes' if plate else 'NO'}, words {photos}/{len(words)}, repeated {len(urls) - len(set(urls))}, word = plate {sum(1 for u in urls if u == plate)}")
    print('  voice: ' + ', '.join(f'{k} {a}/{n}' for k, (a, n) in voice_counts(w).items()))


def create(email, out, goal):
    login(email)
    current = call('GET', '/plans/current')['data']
    if current is not None and current['status'] in ('active', 'overdue', 'ready', 'building'):
        print('existing plan', current['id'], current['status'], '— deleting')
        call('DELETE', f"/plans/{current['id']}")
    t0 = time.time()
    build = call('POST', '/plans', {'goal_text': goal, 'target_lang': 'en', 'level': 'beginner', 'days_total': 3})['data']
    pid = build['id']
    while build['status'] == 'building':
        time.sleep(2)
        build = call('GET', f'/plans/{pid}/build')['data']
    t_plan = time.time()
    print(f"plan {pid} {build['status']} in {t_plan - t0:.1f} s, cost {build.get('cost_usd')}")
    if build['status'] != 'ready':
        sys.exit(1)
    plan = call('GET', f'/plans/{pid}')['data']
    while plan['days'][0]['lesson_status'] != 'ready':
        time.sleep(1)
        plan = call('GET', f'/plans/{pid}')['data']
    t_day = time.time()
    print(f'day 1 ready {t_day - t_plan:.1f} s after the plan (lesson + photos), {t_day - t0:.1f} s from «create»')
    w = window(pid)
    describe(w)
    with open(f'{out}/live_plan.json', 'w') as f:
        json.dump({'plan_id': pid, 'created_at': t0, 'plan_ready_s': t_plan - t0, 'day_ready_s': t_day - t_plan}, f, indent=1)
        f.write('\n')
    with open(f'{out}/live_window_at_ready.json', 'w') as f:
        json.dump(w, f, ensure_ascii=False, indent=1)
        f.write('\n')


def words(pid):
    queue = [c for c in call('POST', f'/plans/{pid}/days/1/open')['data']['cards'] if c['stage'] == 'words' and c['result'] is None]
    answered = 0
    while queue:
        c = queue.pop(0)
        res = call('POST', f"/plans/{pid}/days/1/cards/{c['id']}/answer", {'result': 'passed', 'attempts': 1})['data']
        answered += 1
        if res.get('requeued'):
            queue.append(res['requeued'])
        time.sleep(0.55)
    call('POST', f'/plans/{pid}/days/1/stages/words/close')
    print('answered', answered, 'word cards; «Слова» closed')


def wait_voice(pid, minutes):
    deadline = time.time() + minutes * 60
    while True:
        w = window(pid)
        counts = voice_counts(w)
        done = all(a == n for a, n in counts.values())
        print(time.strftime('%H:%M:%S'), ', '.join(f'{k} {a}/{n}' for k, (a, n) in counts.items()))
        if done or time.time() > deadline:
            return
        time.sleep(30)


if __name__ == '__main__':
    cmd = sys.argv[1]
    if cmd == 'create':
        create(sys.argv[2], sys.argv[3], sys.argv[4] if len(sys.argv) > 4 else 'Иду к врачу: болит спина, нужно описать боль и понять назначения')
    else:
        login(sys.argv[2])
        if cmd == 'window':
            describe(window(sys.argv[3]))
        elif cmd == 'words':
            words(sys.argv[3])
        elif cmd == 'voice':
            wait_voice(sys.argv[3], float(sys.argv[4]) if len(sys.argv) > 4 else 60)
