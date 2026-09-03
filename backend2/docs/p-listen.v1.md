# P-Listen — разогрев на слух для входа в план, v1

Реестр: **P-Listen**. Автор текста — архитектор (наряд ENTRY-2, Ч-3), внесён дословно.
Файл промпта: `app/Modules/Generation/Infrastructure/Prompt/plan_listen.v1.md`.

---

## ТЕЛО ПРОМПТА (verbatim)

You prepare a one-minute listening warm-up for a language-learning app. The user is about to face a real situation in {{target_lang}}; support language is {{support_lang}}, level {{level}}.
Goal, in the user's words: {{goal}}
Return ONE JSON object, nothing else:
{ "lines": [ { "text": "a short line the other person would realistically say in this situation, in {{target_lang}}, level-appropriate, spoken register", "translation": "translation into {{support_lang}}", "place": "2–4 words in {{support_lang}} naming where/when it sounds (e.g. 'на стойке', 'по телефону')" } ] }
Guide: 3 lines, each 4–12 words, three different moments of the situation, no numbers-heavy lines, no proper names. Counts are guidance, not law. Answer with the JSON object only.

---

## ПРИЛОЖЕНИЕ ДЛЯ СЕССИИ (в промпт не входит)

**Валидатора нет и не будет.** Единственное, что может быть не так с репликой разогрева, — что её
нет. Пустой список, поломанная форма и упавший вендор означают для входа одно и то же: шаг молча
не предлагается, и человек идёт к дате. Схема `PlanSchemas::listen()` держит форму, сервис
(`PlanListenService`) выбрасывает реплику без текста или без перевода («Показать текст» показал бы
пустоту) и режет ответ по трём.

**Учёт с `plan_id = NULL`.** Это единственный плановый промпт, который зовётся ДО существования
плана: шаг стоит между уровнем и датой, а план создаётся кнопкой после даты. Строка учёта
(`PlanSpend::CALL_LISTEN`) пишется всегда, когда вызов состоялся, — отбитый ответ стоил столько же,
сколько принятый. Вендорская ошибка ДО ответа строки не пишет: это не покупка.

**Что делает с ответом клиент.** Реплики живут одну минуту, на устройстве не сохраняются и
терминами не становятся. Обратно на сервер едут пары «реплика + понял/не совсем»
(`POST /plans` → `listening[]`); вердикт («упор на понимание» / «на говорение») считает сервер
(`ListeningDiagnostics::emphasis()`) и на провод не пускает — клиент, который мог бы его прислать,
мог бы прислать и такой, который спорит со своими же строками.

**Куда доезжает результат.** `{{diagnostics}}` в P1 (реплики с ответами + вывод прозой) и
`{{balance}}` в P2 v0.4.1 (одно слово, правило раскладки полок — в тексте промпта).

Живой прогон: `docs/research/entry-2-run.md` §2.
