#!/usr/bin/env python3
"""LANG-1b §1 — «было/стало» по 53 дням из двух прогонов replay-gates.php (код main и код ветки). Моделей не зовёт.

    python3 docs/research/lang-1b/tools/compare-replay.py docs/research/lang-1b/replay > docs/research/lang-1b/replay/table.md

Три меры провала на каждой стороне:
  - «карточек ≥ 3» — фатальных карточек на сыром ответе больше, чем чинит P2R (мера baseline LANG-1, «62 % ru→en»);
  - «переигровка» — ворота кода этой стороны, P2R отвечает ЗАПИСАННАЯ починка той же карточки; карточка, которую запись
    не чинила, возвращается как была (пессимистично) — такой день помечен «без записи»;
  - «точно» — дни, где каждая починка переигровки нашлась в записи: их исход доказан.
Автопересборка (§1): новый ответ модели — независимая попытка; у дней без второго ответа — оценка p², у сборок части D —
цепочка по записанным сборкам пары (сборка n упала → пересборка = сборка n+1).
"""
import json
import sys
from collections import Counter, OrderedDict

base = sys.argv[1] if len(sys.argv) > 1 else 'docs/research/lang-1b/replay'
before = json.load(open(f'{base}/before.json'))['days']
after = json.load(open(f'{base}/after.json'))['days']
bk = {d['key']: d for d in before}

SHORT = {
    'options.form_mismatch': 'form', 'exchange.second_question': '2nd_q', 'pronunciation.foreign_script': 'script',
    'filler.ungrammatical': 'filler', 'line.ne_frame': 'ne_frame', 'vocab.known_repeat': 'known_word',
    'frame.known_repeat': 'known_frame', 'check.shape': 'check', 'listening.shape': 'listening',
    'exchange.shape': 'x.shape', 'exchange.repeats': 'repeats',
}


def cards(d):
    c = d['raw']['fatal_cards']
    return 99 if c == 'unrepairable' else int(c)


def codes(d):
    c = Counter(SHORT.get(f['code'], f['code']) for f in d['raw']['fatal'])
    return ', '.join(f'{k}×{v}' if v > 1 else k for k, v in c.most_common()) or '—'


def form_subs(d):
    c = Counter(f['sub'] for f in d['raw']['form_mismatch'])
    return ', '.join(f'{k}×{v}' if v > 1 else k for k, v in c.most_common()) or '—'


GROUPS = OrderedDict([('ru-en', 'ru→en (baseline LANG-1)'), ('scouting', 'разведка LANG-1'), ('live', 'часть D LANG-1 (живые сборки)')])

print('# LANG-1b §1 · переигровка ворот на 53 сохранённых днях: было (код main) / стало (код ветки)\n')
print('Моделей не звали: сырой ответ урока каждого дня прочитан валидатором стороны, ворота переиграны её `LessonGateKeeper`,')
print('P2R ответила ЗАПИСАННАЯ починка той же карточки. «без записи» — карточка, которую запись не чинила (переигровка вернула')
print('её как была — пессимистично). Инструменты — `tools/replay-gates.php` (кодом main и кодом ветки), `tools/compare-replay.py`.\n')

rows = []
for d in after:
    o = bk[d['key']]
    rows.append((d, o))

for g, title in GROUPS.items():
    print(f'\n## {title}\n')
    print('| день | пара | промт | было: фат. карт. | было: исход | было: фатальные | было: form (подпункты) | стало: фат. карт. | стало: исход | стало: фатальные | стало: partner_fragment | без записи |')
    print('|---|---|---|---|---|---|---|---|---|---|---|---|')
    for d, o in rows:
        if d['group'] != g:
            continue
        pf = len(d['raw']['partner_fragment'])
        un = ', '.join(d['gate']['unrecorded']) or '—'
        print(f"| {d['key']} | {d['pair']} | {d.get('prompt') or '—'} | {o['raw']['fatal_cards']} | {o['gate']['outcome']} | {codes(o)} | {form_subs(o)} "
              f"| {d['raw']['fatal_cards']} | {d['gate']['outcome']} | {codes(d)} | {pf} | {un} |")


def rate(n, total):
    return f'{n}/{total} ({round(100 * n / total)} %)' if total else '—'


print('\n## Итоги\n')
print('| группа | дней | было: карточек ≥ 3 | стало: карточек ≥ 3 | было: переигровка failed | стало: переигровка failed | стало: точно (все починки в записи) — failed | стало с автопересборкой, оценка p² по «карточек ≥ 3» |')
print('|---|---|---|---|---|---|---|---|')
tot = Counter()
for g, title in list(GROUPS.items()) + [('all', 'все 53')]:
    sel = [(d, o) for d, o in rows if g == 'all' or d['group'] == g]
    n = len(sel)
    b3 = sum(1 for d, o in sel if cards(o) >= 3)
    a3 = sum(1 for d, o in sel if cards(d) >= 3)
    bf = sum(1 for d, o in sel if o['gate']['outcome'] == 'failed')
    af = sum(1 for d, o in sel if d['gate']['outcome'] == 'failed')
    exact = [(d, o) for d, o in sel if not d['gate']['unrecorded']]
    ef = sum(1 for d, o in exact if d['gate']['outcome'] == 'failed')
    p = a3 / n if n else 0
    print(f'| {title} | {n} | {rate(b3, n)} | {rate(a3, n)} | {rate(bf, n)} | {rate(af, n)} | {ef} из {len(exact)} | ≈ {round(100 * p * p)} % |')

# form_mismatch before/after, and the pieces
bpieces = [(o['key'], f) for d, o in rows for f in o['raw']['form_mismatch'] if f['sub'] == 'piece']
apieces_fatal = [(d['key'], f) for d, o in rows for f in d['raw']['form_mismatch'] if f['sub'] == 'piece']
afrag = [(d['key'], f) for d, o in rows for f in d['raw']['partner_fragment']]
bform = sum(len(o['raw']['form_mismatch']) for d, o in rows)
aform = sum(len(d['raw']['form_mismatch']) for d, o in rows)
print(f'\n`options.form_mismatch` (фатальный) на сырых ответах: было {bform} находок (из них «кусок» {len(bpieces)}), стало {aform} '
      f'(«кусок» — {len(apieces_fatal)}: подпункта больше нет). Предупреждение `options.partner_fragment` стало: {len(afrag)} находок.')
bsub = Counter(f['sub'] for d, o in rows for f in o['raw']['form_mismatch'])
asub = Counter(f['sub'] for d, o in rows for f in d['raw']['form_mismatch'])
print(f'Подпункты form_mismatch — было: {dict(bsub)}; стало: {dict(asub)}.\n')

# fatal codes before/after
bc = Counter(f['code'] for d, o in rows for f in o['raw']['fatal'])
ac = Counter(f['code'] for d, o in rows for f in d['raw']['fatal'])
print('| фатальный код | было (находок на сырых ответах) | стало |')
print('|---|---|---|')
for code in sorted(set(bc) | set(ac), key=lambda c: -bc[c]):
    print(f'| `{code}` | {bc[code]} | {ac[code]} |')

# the chain of part D: build n failed → the rebuild is build n+1 of the same pair
print('\n## Часть D: цепочки сборок с автопересборкой (сборка n упала → пересборка = записанная сборка n+1)\n')
print('| пара | сборки по порядку: было | стало (одна сборка) | стало с автопересборкой: день — нажатий «ещё раз» до ready |')
print('|---|---|---|---|')
live = OrderedDict()
for d, o in rows:
    if d['group'] == 'live':
        live.setdefault(d['pair'], []).append((d, o))
for pair, seq in live.items():
    seq.sort(key=lambda t: t[0]['build'])
    was = ' '.join('✓' if o['gate']['outcome'] == 'ready' else '✗' for d, o in seq)
    now = ' '.join('✓' if d['gate']['outcome'] == 'ready' else '✗' for d, o in seq)
    # walk: a day is one build + one automatic rebuild; a failed day needs the learner's «ещё раз»
    i, presses, days = 0, 0, []
    while i < len(seq):
        first = seq[i][0]['gate']['outcome'] == 'ready'
        if first:
            days.append(f'ready со сборки {i + 1}')
            break
        if i + 1 < len(seq) and seq[i + 1][0]['gate']['outcome'] == 'ready':
            days.append(f'ready с автопересборки (сборка {i + 2})')
            break
        presses += 1
        i += 2
    outcome = days[0] if days else 'не собрался на записанных ответах'
    print(f'| {pair} | {was} | {now} | {outcome}; нажатий «ещё раз»: {presses} |')

print('\n## Все находки `options.partner_fragment` (стало) — на проверку «ложных»\n')
print('| день | адрес | находка |')
print('|---|---|---|')
for key, f in afrag:
    print(f"| {key} | {f['address']} | {f['detail']} |")

print('\n## «Кусок» было — что из него ушло в исключения (число, время, имя, сосчитанное) и что стало предупреждением\n')
print('| день | адрес | было (фатально) | стало |')
print('|---|---|---|---|')
ak = {(d['key'], f['address']) for d, o in rows for f in d['raw']['partner_fragment']}
for key, f in bpieces:
    now = 'предупреждение `options.partner_fragment`' if (key, f['address']) in ak else 'не кусок (исключение)'
    print(f"| {key} | {f['address']} | {f['detail']} | {now} |")
