# plan_listen.v1.1 — разогрев на слух и продолжения цели для входа в план (P-Listen)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P-Listen**. Рендерится
> `PlanPromptLibrary`, вызывается `PlanListenService`, ответ судится схемой
> `PlanSchemas::listen()` и одним правилом сервиса: пусто — значит блока нет.
> Всё, что выше первого `---`, до модели не доезжает (см. `PlanPromptLibrary::body()`).
>
> **Текст написан архитектором** (наряд ENTRY-2, Ч-3 и его доработка), внесён дословно.
>
> **v1.1 против v1 — у промпта появилась вторая работа.** «Дописать за тебя» на экране цели
> перестало быть статикой: заготовки жили на чужой теме и выглядели поломкой (живой случай —
> цель про IT-собеседование, подсказка про больную спину). Нового промпта заводить не стали:
> продолжения цели спрашивают у того же вызова, что и реплики.
>
> Отсюда `{{target_lang}}`, который **может быть пустым**: на шаге цели язык ещё не выбран, и
> тогда вызов возвращает ТОЛЬКО продолжения. Полный ответ с репликами приходит после шага
> языка, как и раньше.
>
> Это ЕДИНСТВЕННЫЙ плановый промпт, который зовётся ДО того, как план существует, поэтому
> строка учёта пишется с `plan_id = NULL` (`PlanSpend::CALL_LISTEN`).
>
> **Тихий отбой — часть контракта.** Ни одна ошибка этого промпта не видна человеку: упал
> вендор, вернулась не та форма, вернулся пустой список — шаг слуха молча не предлагается, а
> блок «Дописать за тебя» молча не показывается. Необязательное, что извиняется за себя, хуже,
> чем его отсутствие.

Плейсхолдеры: `{{target_lang}}` (может быть пустым), `{{support_lang}}`, `{{level}}`, `{{goal}}`.

---

You prepare a one-minute listening warm-up for a language-learning app. The user is about to face a real situation in {{target_lang}}; support language is {{support_lang}}, level {{level}}.
Target language: {{target_lang}} — may be empty if the user has not chosen it yet.
Goal, in the user's words: {{goal}}
Return ONE JSON object, nothing else:
{ "lines": [ { "text": "a short line the other person would realistically say in this situation, in {{target_lang}}, level-appropriate, spoken register", "translation": "translation into {{support_lang}}", "place": "2–4 words in {{support_lang}} naming where/when it sounds (e.g. 'на стойке', 'по телефону')" } ], "continuations": ["two short natural continuations of the user's goal, in {{support_lang}} — what people usually add to such a goal: who they will talk to, what they must understand or be able to say. Each must continue the user's own sentence naturally; never restate what is already written."] }
Guide: 3 lines, each 4–12 words, three different moments of the situation, no numbers-heavy lines, no proper names. Counts are guidance, not law. If the target language is empty, return lines as an empty array and fill only continuations. Answer with the JSON object only.
