# LANG-1 · живой день · pl-en

Ученик `qa-lang1-live-pl-en-0926@wt.test` · план `01M3DGPN35MXD4HJEF9B5H1868` · база `wordtrainer_e2e_test` · выгружено 2026-09-26T00:18:38Z

Цель (на родном): «Zapisuję się do lekarza: od trzech dni boli mnie gardło i mam gorączkę. Muszę wybrać dogodny termin i wyjaśnić, co mi dolega» · уровень beginner · дней 3

- сервер фазы create: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос выкл · фото (по умолчанию)
- сервер фазы dump: `/wt/public` · база `wordtrainer_e2e_test` · очередь sync · модель (по умолчанию) · голос вкл · фото (по умолчанию)

### Сборка

План **ready** за 53.1 с (plan-builder-v2, gpt-5.4-2026-03-05, попыток 1) · день 1 «Rejestracja» — **failed** (`fatal: options.form_mismatch`) за 53.2 с от POST (урок `lesson_day.v4.8`, модель gpt-5.4-2026-03-05, попыток 1) · `lang.pack_missing` +0 (счётчики не сдвинулись).

- `lang.pack_missing` · lesson_day.v4.5 · counted: 14 → 14 (0)
- `lang.pack_missing` · lesson_day.v4.7 · counted: 174 → 174 (0)
- находки урока (`checks_json`): `exchange.second_question` @x1, `pronunciation.foreign_script` @B7, `pronunciation.script` @B7, `frame.no_end_punct` @p2, `frame.unresolved_pronoun` @p3, `frame.no_end_punct` @p4, `frame.no_end_punct` @p6, `frame.no_end_punct` @p7, `frame.no_end_punct` @p8, `filler.one_in_dialogue` @p3.f1, `line.ne_frame` @B3, `variant.longer` @B3, `options.form_mismatch` @x4.check, `check.about_learner` @x7.check, `options.form_mismatch` @x7.check, `vocab.used_in_wrong` @v8
- сцены: 1. «Rejestracja» / «Reception desk» — failed · 2. «U lekarza» / «Doctor visit» — pending

### Проход дня 1

Не проходился.

### Разговор

Не начинался.

### Отказы сторожей роли

Нет — ни одного отказа (`conversation_rejections` пуст). Страж перевода (`native_missing`): 0.

### Деньги

| что | кредиты · символы · $ |
|---|---|
| голос дня 1 (0 строк) | 0 · 0 · $0.0000 |
| голос разговора (0 реплик) | 0 · 0 · $0.0000 |
| **голос всего** | **0 · 0 · $0.0000** |

| модели | $ |
|---|---|
| план | $0.0123 |
| урок дня 1 (с починками и судьёй швов) | $0.0786 |
| судья окна (0 решений модели из 0, цена из `response.judge` карточек) | $0.0000 |
| разговор (ходы роли, `model_cost_usd` — с отказанными попытками) | $0.0000 |
| **модели всего** | **$0.0909** |

Сверка судьи: вызовов `judge` в журнале model_calls за окно прохода — 0 на $0.0000 (сюда попал бы и судья швов чужой сборки в той же базе).
