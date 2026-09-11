#!/usr/bin/env python3
"""Regenerate docs/plan-ui-glossary.md from mobile/lib/l10n/app_ru.arb (наряд PLAN-UI)."""
import json, os, re
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
arb = json.load(open(os.path.join(root, 'mobile/lib/l10n/app_ru.arb'), encoding='utf-8'))
path = os.path.join(root, 'docs/plan-ui-glossary.md')
text = open(path, encoding='utf-8').read()
head = text.split('| Ключ | Подпись | Где стоит |')[0]
rows = ['| Ключ | Подпись | Где стоит |', '|---|---|---|']
for k, v in arb.items():
    if k.startswith('@') or not k.startswith('plan'):
        continue
    desc = (arb.get('@' + k) or {}).get('description', '')
    rows.append('| `%s` | %s | %s |' % (k, v.replace('|', '\\|'), desc.replace('|', '\\|')))
open(path, 'w', encoding='utf-8').write(head + '\n'.join(rows) + '\n')
print('rows:', len(rows) - 2)
