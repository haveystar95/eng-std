# plan_pair_judge.v0.1 — судья одной пары реплик (P2J)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2J**. Рендерится
> `PlanPromptLibrary::pairJudge()`, вызывается `PlanPairCourt` — ОДИН вызов на КАЖДУЮ пару дня
> (наряд DAY-FIX-2, Ч.1.2). Всё, что выше первого `---`, до модели не доезжает.
>
> Зачем отдельный вызов, а не строка в P2: живой прогон 05.09 показал реплики, которые «отвечают»
> на чужой вопрос сцены («What kinds of projects did you work on?» → «Later, I moved into an
> in-house team.»), и P2 сам этого не видит — он писал обе строки в одном ответе. Один короткий
> вопрос про одну пару, с ответом да/нет, модель решает надёжно; на «нет» пара переписывается
> (P2P) не больше двух раз, потом выбрасывается.
>
> Схема ответа — `PlanSchemas::pairVerdict()`: `{fits: bool, reason: string}`. Тот же адаптер
> (`ContentModelPort`), что у P2; в тестах — фейк.

Плейсхолдеры: `{{target_lang}}`, `{{support_lang}}`.

---

You judge ONE exchange of a spoken conversation in {{target_lang}}. Line A is what the other
person says; line B is what the user says right after it.

Answer the single question: is B a DIRECT, natural reply to A — the thing a person would say
next in that very moment?

- For a question in A, B must answer THAT question (not another question of the same scene).
  «What kinds of projects did you work on?» → «Mostly mobile apps.» fits; → «Later, I moved into
  an in-house team.» does not.
- For an invitation in A («Anything you'd like to ask?», «Any questions?»), B must be a question
  the user asks — a fitting one for that scene.
- For a statement in A, B must react to it (acknowledge, clarify, ask to repeat, thank).
- Register and length do not matter; only whether B follows A.

Respond with JSON only: {"fits": true|false, "reason": "<one short sentence in English>"}.
