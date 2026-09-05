# plan_pair_rewrite.v0.1 — переписать мою реплику пары так, чтобы она отвечала (P2P)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2P**. Рендерится
> `PlanPromptLibrary::pairRewrite()`, вызывается `PlanPairCourt` ТОЛЬКО после того, как судья
> (P2J) сказал «нет» — не больше двух раз на пару, потом пара выбрасывается (наряд DAY-FIX-2,
> Ч.1.2). Всё, что выше первого `---`, до модели не доезжает.
>
> Переписывается ОДНА реплика — `you` пары. Реплика собеседника (A) не трогается: она уже
> прошла свои гейты, и адрес пары держится на ней. Схема ответа — `PlanSchemas::pairYou()`: один
> `you`-элемент той же формы, что в P2 (`frame` + `filler`, `translation`, `transliteration`,
> `skill_ref`).
>
> Переписанная реплика затем судится ВСЕМИ гейтами дня (`PlanDayValidator`) как любая другая:
> клоны, размер, ключ в переводе, стоп-список. Судья пар ничего из этого не заменяет.

Плейсхолдеры: `{{target_lang}}`, `{{support_lang}}`, `{{level}}`.

---

You rewrite ONE line of a spoken conversation in {{target_lang}} for a language learner
(level: {{level}}). Translations are in {{support_lang}}.

You are given line A (what the other person says), the user's current line B, and why B does
not follow A. Write a NEW line B that is a DIRECT, natural reply to A:

- for a question in A — answer THAT question;
- for an invitation to ask («Anything you'd like to ask?») — ask one fitting question;
- for a statement in A — react to it.

Rules for the new line:
- 3–8 words when assembled. Spoken register, one thought.
- "frame" with at most one `___` gap and "filler" — the key that stands in the gap. Prefer as the
  key one of the DAY WORDS given below, character for character; a line said whole has no `___`
  and an empty "filler".
- Never a basic word as the key (numbers, weekdays, pronouns, be/have/go and the like), never a
  proper name.
- "translation": the WHOLE assembled sentence in {{support_lang}}, containing the translation of
  the key.
- "transliteration": a practical reading of the assembled line in the support alphabet when the
  scripts differ; otherwise an empty string.
- Keep "skill_ref" as given unless the new line clearly serves another skill of the scene.

Respond with JSON only: {"frame": ..., "filler": ..., "translation": ..., "transliteration": ..., "skill_ref": ...}.
