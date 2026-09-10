#!/usr/bin/env python3
"""Drive the plan API for the acceptance run of PLAN-UI (QA account, local server).

  plan_drive.py login                      -> prints token
  plan_drive.py current                    -> GET /plans/current (short)
  plan_drive.py create "<goal>" <days> <level> [YYYY-MM-DD]  -> creates, polls build, prints plan id + status
  plan_drive.py start <plan>               -> POST start
  plan_drive.py wait-lesson <plan> <n>     -> poll until day n lesson ready
  plan_drive.py answer <plan> <n> [count]  -> open day n, answer `count` cards passed (all when omitted), close stages when complete
  plan_drive.py close <plan> <n>           -> close day n (answers everything first)
  plan_drive.py delete <plan>
  plan_drive.py finish <plan>
"""
import json, sys, time, urllib.request, urllib.error

BASE = 'http://localhost:8001/api/v1'
EMAIL = 'qa@wt.test'
TOKEN_FILE = '/private/tmp/claude-502/-Users-yalantisdenys-eng-std/501f5e0e-4074-4c92-b5b6-6828916ba30d/scratchpad/qa_token.txt'


def call(method, path, body=None, token=None):
    data = None if body is None else json.dumps(body).encode()
    req = urllib.request.Request(BASE + path, data=data, method=method)
    req.add_header('Accept', 'application/json')
    req.add_header('Content-Type', 'application/json')
    if token:
        req.add_header('Authorization', 'Bearer ' + token)
    try:
        with urllib.request.urlopen(req, timeout=120) as r:
            raw = r.read()
            return r.status, (json.loads(raw) if raw else None)
    except urllib.error.HTTPError as e:
        raw = e.read()
        try:
            return e.code, json.loads(raw)
        except Exception:
            return e.code, raw.decode(errors='replace')


def token():
    try:
        return open(TOKEN_FILE).read().strip()
    except FileNotFoundError:
        st, body = call('POST', '/auth/dev', {'email': EMAIL, 'device_name': 'drive'})
        assert st == 200, (st, body)
        open(TOKEN_FILE, 'w').write(body['token'])
        return body['token']


def short_plan(p):
    if p is None:
        return 'null'
    days = ', '.join(f"{d['number']}:{d['type'][:3]}/{d['status']}/{d['slot']['code']}/{d.get('lesson_status')}" for d in p['days'])
    return f"{p['id']} {p['status']} days_total={p['days_total']} current={p['current_day'] and p['current_day']['number']} event={p.get('event_date')} until={p.get('until_phrase')!r} overdue={p.get('overdue_native')!r} coll={p.get('collection_id')}\n  {days}"


def main():
    cmd = sys.argv[1]
    t = token()
    if cmd == 'login':
        print(t)
    elif cmd == 'current':
        st, body = call('GET', '/plans/current', token=t)
        print(st, short_plan(body['data']) if st == 200 else body)
    elif cmd == 'list':
        st, body = call('GET', '/plans', token=t)
        for p in body['data']:
            print(p['id'], p['status'], p['days_total'], p.get('title_native'), p['goal_text'][:40])
    elif cmd == 'create':
        goal, days, level = sys.argv[2], int(sys.argv[3]), sys.argv[4]
        date = sys.argv[5] if len(sys.argv) > 5 else None
        st, body = call('POST', '/plans', {'goal_text': goal, 'target_lang': 'en', 'level': level, 'days_total': days, 'event_date': date}, token=t)
        print(st, body)
        pid = body['data']['id']
        while True:
            st, b = call('GET', f'/plans/{pid}/build', token=t)
            print(st, b['data']['status'], b['data'].get('unclear_reason'), b['data'].get('fail_reason'))
            if b['data']['status'] != 'building':
                break
            time.sleep(3)
        st, b = call('GET', f'/plans/{pid}', token=t)
        print(short_plan(b['data']))
    elif cmd == 'start':
        st, body = call('POST', f'/plans/{sys.argv[2]}/start', token=t)
        print(st, short_plan(body['data']) if st == 200 else body)
    elif cmd == 'wait-lesson':
        pid, n = sys.argv[2], int(sys.argv[3])
        while True:
            st, b = call('GET', f'/plans/{pid}', token=t)
            day = [d for d in b['data']['days'] if d['number'] == n][0]
            print(day['lesson_status'])
            if day['lesson_status'] in ('ready', 'failed', None):
                break
            time.sleep(5)
    elif cmd in ('answer', 'close'):
        pid, n = sys.argv[2], int(sys.argv[3])
        count = int(sys.argv[4]) if cmd == 'answer' and len(sys.argv) > 4 else None
        st, body = call('POST', f'/plans/{pid}/days/{n}/open', token=t)
        print('open', st, body if st != 200 else body['data']['status'])
        if st != 200:
            return
        answered = 0
        while True:
            st, body = call('GET', f'/plans/{pid}/days/{n}/cards', token=t)
            cards = [c for c in body['data']['cards'] if c['result'] is None]
            if not cards:
                break
            c = cards[0]
            st, r = call('POST', f'/plans/{pid}/days/{n}/cards/{c["id"]}/answer', {'result': 'passed', 'attempts': 1}, token=t)
            if st != 200:
                print('answer', st, r)
                # stage boundary: close the finished stage and go on
                stage = c['stage']
                st2, r2 = call('POST', f'/plans/{pid}/days/{n}/stages/{stage}/close', token=t)
                print('close stage', stage, st2, r2 if st2 != 200 else 'ok')
                if st2 != 200:
                    return
                continue
            answered += 1
            if count is not None and answered >= count:
                break
            # close a stage whose cards are all answered
            remaining_same = [x for x in cards[1:] if x['stage'] == c['stage']]
            if not remaining_same:
                st2, r2 = call('POST', f'/plans/{pid}/days/{n}/stages/{c["stage"]}/close', token=t)
                print('close stage', c['stage'], st2, r2 if st2 != 200 else 'ok')
        print('answered', answered)
        if cmd == 'close':
            st, body = call('POST', f'/plans/{pid}/days/{n}/close', token=t)
            print('close day', st, body if st != 200 else 'ok')
    elif cmd == 'delete':
        st, body = call('DELETE', f'/plans/{sys.argv[2]}', token=t)
        print(st, body)
    elif cmd == 'finish':
        st, body = call('POST', f'/plans/{sys.argv[2]}/finish', token=t)
        print(st, body if st != 200 else short_plan(body['data']))
    elif cmd == 'room':
        st, body = call('GET', f'/plans/{sys.argv[2]}/days/{sys.argv[3]}', token=t)
        d = body['data']
        print(st, d['day']['status'], [(s['stage'], s['done'], s['total'], s['state']) for s in d['stages']], d.get('metrics'))


if __name__ == '__main__':
    main()
