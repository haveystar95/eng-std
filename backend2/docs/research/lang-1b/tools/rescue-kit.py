#!/usr/bin/env python3
"""LANG-1b §2 — the rescue kit written into the language packs (`config/lesson/lang/<code>.php`, key `rescue`).

One matrix, six meanings × ten languages: a column is both a TARGET's line (what a learner of that language says) and the
NATIVE translation shown to a learner whose language it is. Row 1 is each pack's `rescue_line` (the talk's «Sorry?»).
Every target pack (en pl ro es it de fr) gets `[{target, native: {ru, uk, be, pl, ro, es, it, de, fr}}]`; a language that is
only ever a learner's (ru uk be) gets the no-op `[]`. Re-running replaces the block it wrote.

    python3 docs/research/lang-1b/tools/rescue-kit.py
"""
import os
import re

NBSP = ' '
LANGS = ['en', 'ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']
TARGETS = ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']
NATIVES = ['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']

MATRIX = [
    {  # 1 — «Sorry?»: every pack's own `rescue_line`
        'en': 'Sorry?', 'ru': 'Простите?', 'uk': 'Перепрошую?', 'be': 'Прабачце?', 'pl': 'Słucham?', 'ro': 'Poftim?',
        'es': '¿Perdón?', 'it': 'Scusi?', 'de': 'Wie bitte?', 'fr': f'Pardon{NBSP}?',
    },
    {  # 2 — «Could you say that more slowly, please?»
        'en': 'Could you say that more slowly, please?', 'ru': 'Можно помедленнее, пожалуйста?',
        'uk': 'Можна повільніше, будь ласка?', 'be': 'Можна павольней, калі ласка?', 'pl': 'Proszę mówić trochę wolniej.',
        'ro': 'Puteți vorbi mai rar, vă rog?', 'es': '¿Puede hablar más despacio, por favor?',
        'it': 'Può parlare più lentamente, per favore?', 'de': 'Können Sie bitte langsamer sprechen?',
        'fr': f"Vous pouvez parler plus lentement, s'il vous plaît{NBSP}?",
    },
    {  # 3 — «I don't understand.»
        'en': "I don't understand.", 'ru': 'Я не понимаю.', 'uk': 'Я не розумію.', 'be': 'Я не разумею.', 'pl': 'Nie rozumiem.',
        'ro': 'Nu înțeleg.', 'es': 'No entiendo.', 'it': 'Non capisco.', 'de': 'Ich verstehe nicht.', 'fr': 'Je ne comprends pas.',
    },
    {  # 4 — «One moment.»
        'en': 'One moment.', 'ru': 'Одну минуту.', 'uk': 'Хвилинку.', 'be': 'Хвілінку.', 'pl': 'Chwileczkę.', 'ro': 'Un moment.',
        'es': 'Un momento.', 'it': 'Un momento.', 'de': 'Einen Moment.', 'fr': 'Un instant.',
    },
    {  # 5 — «Can you write it down?»
        'en': 'Can you write it down?', 'ru': 'Можете это записать?', 'uk': 'Можете це записати?', 'be': 'Можаце гэта запісаць?',
        'pl': 'Proszę mi to zapisać.', 'ro': 'Îmi puteți scrie asta?', 'es': '¿Me lo puede escribir?', 'it': 'Me lo può scrivere?',
        'de': 'Können Sie mir das aufschreiben?', 'fr': f"Vous pouvez me l'écrire{NBSP}?",
    },
    {  # 6 — «Thank you.»
        'en': 'Thank you.', 'ru': 'Спасибо.', 'uk': 'Дякую.', 'be': 'Дзякуй.', 'pl': 'Dziękuję.', 'ro': 'Mulțumesc.',
        'es': 'Gracias.', 'it': 'Grazie.', 'de': 'Danke.', 'fr': 'Merci.',
    },
]

HEADER = '    // THE RESCUE KIT (наряд LANG-1b §2)'


def php(text: str) -> str:
    if NBSP in text:
        body = text.replace('\\', '\\\\').replace('"', '\\"').replace('$', '\\$').replace(NBSP, '\\u{00A0}')
        return f'"{body}"'
    return "'" + text.replace('\\', '\\\\').replace("'", "\\'") + "'"


def block(code: str) -> str:
    if code not in TARGETS:
        return (f"{HEADER}: the no-op — {code} is only ever a learner's language here, and a kit is its TARGET's\n"
                "    // (`RescueKits`); its translations stand in the kits of the targets.\n"
                "    'rescue' => [],\n")
    lines = [f"{HEADER}: the six lines a learner of this language says when stuck, each with its translation into",
             "    // every learner's language of the plan. Row 1 is `rescue_line`. Said in the learner's voice, filed by (target,",
             "    // gender, voice, line) — `RescueKits`. The same matrix writes every pack: `docs/research/lang-1b/tools/rescue-kit.py`.",
             "    'rescue' => ["]
    for row in MATRIX:
        natives = ', '.join(f"'{n}' => {php(row[n])}" for n in NATIVES)
        lines.append(f"        ['target' => {php(row[code])}, 'native' => [{natives}]],")
    lines.append('    ],')
    return '\n'.join(lines) + '\n'


def main() -> None:
    base = os.path.join(os.path.dirname(__file__), '..', '..', '..', '..', 'config', 'lesson', 'lang')
    for code in LANGS:
        path = os.path.normpath(os.path.join(base, f'{code}.php'))
        src = open(path, encoding='utf-8').read()
        # the block written before, if any
        src = re.sub(re.escape(HEADER) + r".*?\n    'rescue' => (?:\[\],\n|\[\n.*?\n    \],\n)\n", '', src, flags=re.S)
        m = re.search(r"\n    'rescue_line' => [^\n]*\n", src)
        if m is None:
            raise SystemExit(f'{code}: no rescue_line')
        at = m.end()
        src = src[:at] + '\n' + block(code) + src[at:]
        open(path, 'w', encoding='utf-8').write(src)
        print(code, 'target' if code in TARGETS else 'no-op')


if __name__ == '__main__':
    main()
