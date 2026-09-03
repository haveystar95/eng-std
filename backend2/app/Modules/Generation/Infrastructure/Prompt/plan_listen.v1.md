# plan_listen.v1 — разогрев на слух для входа в план (P-Listen)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P-Listen**. Рендерится
> `PlanPromptLibrary`, вызывается `PlanListenService`, ответ судится схемой
> `PlanSchemas::listen()` и одним правилом сервиса: пусто — значит шага нет.
> Всё, что выше первого `---`, до модели не доезжает (см. `PlanPromptLibrary::body()`).
>
> **Текст написан архитектором** (наряд ENTRY-2, Ч-3), внесён дословно.
>
> Это ЕДИНСТВЕННЫЙ плановый промпт, который зовётся ДО того, как план существует: шаг слуха
> стоит между уровнем и датой, а план создаётся кнопкой «Собрать план» после даты. Поэтому
> строка учёта пишется с `plan_id = NULL` (`PlanSpend::CALL_LISTEN`), и это не дыра в учёте, а
> его честная форма: вызов был, плана ещё нет.
>
> **Тихий отбой — часть контракта.** Ни одна ошибка этого промпта не видна человеку: упал
> вендор, вернулась не та форма, вернулся пустой список — шаг молча не предлагается, и вход
> идёт к дате. Необязательный шаг, который извиняется за себя, хуже, чем его отсутствие.

Плейсхолдеры: `{{target_lang}}`, `{{support_lang}}`, `{{level}}`, `{{goal}}`.

---

You prepare a one-minute listening warm-up for a language-learning app. The user is about to face a real situation in {{target_lang}}; support language is {{support_lang}}, level {{level}}.
Goal, in the user's words: {{goal}}
Return ONE JSON object, nothing else:
{ "lines": [ { "text": "a short line the other person would realistically say in this situation, in {{target_lang}}, level-appropriate, spoken register", "translation": "translation into {{support_lang}}", "place": "2–4 words in {{support_lang}} naming where/when it sounds (e.g. 'на стойке', 'по телефону')" } ] }
Guide: 3 lines, each 4–12 words, three different moments of the situation, no numbers-heavy lines, no proper names. Counts are guidance, not law. Answer with the JSON object only.
