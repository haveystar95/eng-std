# plan_pair_rewrite.v0.2 — переписать мою реплику пары по канону качества (P2P)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2P**. Рендерится
> `PlanPromptLibrary::pairRewrite()`, вызывается `PlanPairCourt` ТОЛЬКО после того, как судья
> (P2J v0.2) сказал «нет» — не больше двух раз на пару, потом пара выбрасывается. Всё, что выше
> первого `---`, до модели не доезжает.
>
> **v0.2 против v0.1 (наряд GEN-1, Ч.5.2):** переписчик получает, КАКОЙ из четырёх вопросов
> судьи провалила реплика, и те же правила канона, что и P2 v0.7: никаких переспросов в ответе,
> простота уровня числом, перевод ровно по смыслу, `speaking_keys[]` в ответе. Схема —
> `PlanSchemas::pairYou()`.
>
> Переписывается ОДНА реплика — `you` пары. Реплика собеседника (A) не трогается: она уже прошла
> свои гейты, и адрес пары держится на ней. Переписанная реплика затем судится P2J заново и всеми
> гейтами дня (`PlanDayValidator`), как любая другая.

Плейсхолдеры: `{{target_lang}}`, `{{support_lang}}`, `{{level}}`.

---

You rewrite ONE line of a spoken conversation in {{target_lang}} for a language learner
(level: {{level}}). Translations are in {{support_lang}}.

You are given line A (what the other person says, with its translation), the learner's current
line B (with its translation), the pair's `kind`, and `why_B_failed` — which check B failed and
why. Write a NEW line B that passes all four checks:

1. It ANSWERS A on the substance: for a question in A — answer THAT question; for a statement —
   react to it meaningfully. For `kind: "ask"` — B must be a QUESTION the learner asks the other
   person, fitting the scene, ending with a question mark.
2. It is NOT a clarification, a repeat request or a counter-question — never «Sorry, do you
   mean…?», «What does … mean?», «Could you say that again?», «And what about the rent?» (in an
   "answer" pair). Say the thing itself.
3. It fits the level: for "basic" — 4–8 words, plain everyday speech, no office or textbook
   register («I did the planning», never «I was responsible for planning and delivery»); for
   "conversational"/"fluent" — up to 12 words, natural spoken register.
4. Its translation renders EXACTLY the assembled line — nothing added from the scene, nothing
   dropped, nothing literal that reads as nonsense in {{support_lang}}.

Rules for the new line:
- 3–8 words when assembled. Spoken register, one thought.
- "frame" with at most one `___` gap and "filler" — the key that stands in the gap. Prefer as the
  key one of the DAY WORDS given below, character for character; a line said whole has no `___`
  and an empty "filler". No space between the gap and the punctuation after it («… ___.»).
- Never a basic word as the key (numbers, weekdays, pronouns, be/have/go and the like), never a
  proper name.
- "translation": the WHOLE assembled sentence in {{support_lang}}, containing the translation of
  the key.
- "transliteration": a practical reading of the assembled line in the support alphabet when the
  scripts differ; otherwise an empty string.
- "speaking_keys": 1–2 shorter or simpler ways to say the same reply that still count as correct
  when spoken, each 1–6 words in {{target_lang}}, never identical to the assembled line and never
  the filler alone.
- Keep "skill_ref" as given unless the new line clearly serves another skill of the scene.

Respond with JSON only: {"frame": ..., "filler": ..., "translation": ..., "transliteration": ...,
"skill_ref": ..., "speaking_keys": [...]}.
