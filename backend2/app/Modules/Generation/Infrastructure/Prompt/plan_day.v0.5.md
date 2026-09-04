# plan_day.v0.5 — один день Learning Plan, день-СЦЕНА С ДИАЛОГОМ (P2)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2**. Рендерится
> `PlanPromptLibrary`, вызывается `PlanDayComposer`, ответ судит `PlanDayValidator`.
> Всё, что выше первого `---`, до модели не доезжает (см. `PlanPromptLibrary::body()`).
>
> **Текст написан архитектором** (`docs/p2.plan-day.v0.4.md`); канон — `docs/plan-model.md`.
> Тело ниже — байт в байт его текст. Раздел «OUTPUT SHAPE» после тела дописан кодовой сессией:
> это техническая адаптация полей под реестр и strict-схему, а не правило.
>
> **v0.4 против v0.3 — мешок из трёх массивов стал сценой из полок:**
>
> 1. **Полки вместо массивов.** `hear` / `say` / `ask` / `words` / `chunks` / `numbers` вместо
>    `phrases` / `words` / `chunks`. Полка задаёт ЯРУС (`speak` / `understand`) — его выводит
>    сервер, модель ярус не пишет.
> 2. **`skill_ref` обязателен у каждой карточки** — карточка называет умение сцены, которому
>    служит. Умение без карточек — предупреждение дня (`plan_day_skill_uncovered`).
> 3. **Пределы длины по виду** (`card.kind_size`): слово ≤ 3, связка 2–4, реплика ученика 3–8,
>    реплика собеседника ≤ 12.
> 4. **Стоп-список базового** (`card.word_is_basic`): числа, дни недели, семья, цвета,
>    местоимения, be/have/go и подобное карточками не бывают. Фатально от уровня «Понимаю
>    простое», у `zero` — счётчик.
> 5. **Спасательный набор — запретный список.** Пять фраз конфига языкового пакета сервер
>    вшивает сам; день, который их дублирует, ловит `card.clone`.
> 6. **Узоры брака дней 1–2 закрыты явно:** `card.translation_has_gap`, `card.example_is_a_term`,
>    `card.example_skeleton_clone` (перекрёстная подстановка из Д-29).
>
> Механика прежняя и не менялась: `frame` + `filler`, `text` собирает сервер, один запуск —
> один платный вызов, повтор кодами без цитат, P2R (`plan_day_repair.v0.2`) чинит карточки.
>
> **v0.4.1 против v0.4 — две правки архитектора, один бамп (наряд ENTRY-2, Ч-6):**
>
> 1. **Что такое связка, сказано вслух** — в SHELF SPECIFICS к `chunks`. Живой день вернул
>    «see it», «works for» и «the location» карточками полки; две из трёх формы теперь ловят
>    гейты (`card.chunk_is_basic`, `card.chunk_article_pair`), третью — «обрывок без своего
>    объекта» — держит только этот текст, и это осознанный предел механики.
> 2. **`{{balance}}`** — исход шага слуха («понимаю» / «говорю») доезжает до раскладки полок:
>    при упоре на понимание `hear` идёт к верхней границе ориентира, `say` к нижней, и наоборот.
>    Пустой баланс — правило игнорируется, и так работает каждый план без шага слуха.
>
> **v0.5 против v0.4.1 — сцена перестала быть только полками (наряд DAY-2, Ч.1):**
>
> 1. **`dialogue`** — поле верхнего уровня, цепочка ходов: порядок, в котором реплики сцены звучат
>    в жизни. Текст правки — `docs/plan-dialogue.md` §9, внесён дословно. Полки остались тем, чем
>    были: лестница и словарь живут на них, диалог — ПОРЯДОК поверх полок, а не седьмая полка.
> 2. **Гейты диалога** (`PlanDayValidator`): `card.dialogue_ref_invalid` — карточное фатальное,
>    `day.dialogue_missing` и `day.dialogue_not_alternating` — дневные фатальные,
>    `plan_day_dialogue_uncovered` — счётчик. День, написанный до v0.5, диалога не имеет и не
>    ломается: сервер строит цепочку парами по `skill_ref`
>    ({@see \App\Modules\Learning\Domain\Service\PlanDialogueChain}).

Плейсхолдеры: `{{goal}}`, `{{level}}`, `{{target_lang}}`, `{{support_lang}}`,
`{{target_lang_notes}}`, `{{support_lang_notes}}`, `{{scene}}`, `{{known}}`, `{{rescue_kit}}`,
`{{balance}}`.

---

You write the content of ONE learning day for a language app. The day is a SCENE: one
real-life encounter the user must handle in {{target_lang}}. All context and translations
are in {{support_lang}}.

INPUT
- Goal: {{goal}}. Level: {{level}}. Target language: {{target_lang}}. Support language:
  {{support_lang}}.
{{target_lang_notes}}
{{support_lang_notes}}
- The scene (from the plan outline — title, intro, skills with ids, opening_lines,
  entities): {{scene}}
  The intro is already written; do not rewrite or repeat it.
- KNOWN units from previous days of this plan: {{known}}
  Never reintroduce them as cards. You may reuse them inside frames and examples — in NEW
  sentences only.
- Rescue kit of this plan (added by the server, trained daily): {{rescue_kit}}
  Never duplicate these phrases as cards.
- Balance (may be empty): {{balance}} — 'understanding' or 'speaking', the outcome of the user's listening check.

OUTPUT — one JSON object, nothing else:
{
  "hear":    [ ... ],   // "you will hear": lines the other person says. Understanding only. Guide: 4–6.
  "say":     [ ... ],   // "you will answer": short replies the user will say. Guide: 4–6.
  "ask":     [ ... ],   // "you will ask": questions to clarify. Guide: 2–3.
  "words":   [ ... ],   // single words. Together with chunks: guide 6–8.
  "chunks":  [ ... ],   // set combinations of 2–4 words.
  "numbers": [ ... ],   // numbers in live scene context, listening only. Guide: 2–4.
  "dialogue": [
    { "turn": "role", "ref": "hear[0]" },
    { "turn": "you",  "ref": "say[0]" },
    { "turn": "role", "ref": "hear[1]" },
    { "turn": "you",  "ref": "ask[0]" }
  ]
}

EVERY ITEM CARRIES
- "kind": "line" for hear/say/ask, "word", "chunk", "number".
- "skill_ref": the id of ONE skill of the scene this card serves. Every card names its
  skill; every skill of the scene should get at least one card.
- "frame": the sentence with exactly one gap `___` where the key belongs. The gap exists
  ONLY in frame — never in translation, examples or anywhere else.
- "filler": the key — exactly the text of the card, as it stands in the gap. The server
  assembles the final sentence from frame + filler; you never output the assembled text.
- "translation": translation of the WHOLE assembled sentence into {{support_lang}}.
  Translate the finished sentence, not the frame: no `___` in it, and it must contain the
  translation of the key.

SHELF SPECIFICS
- hear: add "speaker": "role". These lines are for understanding only and may be up to 12
  words. Make them the lines this person will really face — use opening_lines as raw
  material, adapted to the scene.
- say / ask: 3–8 words when assembled. If a thought needs more, split it into two lines or
  move the load into a chunk — long utterances are trained by assembly, never as cards.
  Spoken register, natural word order, one thought per line. Lines of the day must be
  combinable with each other and with the day's words and chunks.
- words: at most 3 words. Also carry "example" — a NEW sentence in the scene's world using
  the word (never the word alone, never equal to any line of this day), "example_translation",
  and "image_api_prompt" — a concrete photographable moment. Only words get images.
- chunks: 2–4 words, set combinations (like "make an appointment"). Carry "example" /
  "example_translation" as words do; no image.
  A chunk is a self-sufficient piece of language that lives outside this one sentence (front desk,
  make an appointment, water pressure). NOT a chunk: a fragment cut off from its object (works
  for), an article plus a noun (the location), or a combination of basic words (see it). Translate
  the chunk's text exactly — never add words the text does not contain.
- numbers: "frame" is a natural line the other person says that contains the number, price,
  date or address; "filler" is the number expression as spoken; add "value" — the same
  number as digits (or ISO date). The user's task is listening.
- dialogue — the order in which this scene is actually spoken: an alternating chain of
  role turns (refs into hear) and your turns (refs into say or ask). Guide: 3–5 exchanges
  (6–10 turns). Every ref points to an existing item of this day. Every say and ask item
  should appear in the dialogue at least once; hear items may appear once. Do not invent new
  lines here — dialogue only orders the shelves.

CONTENT RULES
- Everything serves the scene: with today's shelves plus the rescue kit the user must be
  able to walk through this encounter.
- No basic vocabulary as cards: numbers, weekdays, family, colors, pronouns, be/have/go,
  "a little", "enough", "much", "again", "slowly" and the like are never cards. Numbers
  live only in "numbers".
- Proper names are never cards; use the scene's entities as fillers inside frames.
- For say/ask lines, prefer as the key (filler) a word or chunk from today's shelves — the
  gap is where today's vocabulary plugs into living speech. Never use as the key a word the
  day must not teach (basics, numbers, proper names).
- No clones: no two cards of the day assemble into the same text; no example equals a line
  of the day; nothing duplicates the rescue kit or a KNOWN unit.
- Prefer real, specific lines over formulas; formulas must stay a minority of the day.
- Across say/ask include at least one clarifying question and at least one repair move —
  but not the rescue-kit phrases themselves.
- If the support language requires a reading aid (see support notes), add
  "transliteration" to every hear/say/ask/word/chunk item: a practical reading of the
  assembled text in the support alphabet.
- If balance is 'understanding', fill the hear shelf toward the upper end of its guide and say toward the lower end; if 'speaking' — the opposite. If balance is empty, ignore this rule.
- Counts in this prompt are guidance, not law. Never pad with filler cards to hit a number.
- Answer with the JSON object only.

---

## OUTPUT SHAPE (technical, added by the code session)

The six shelves hold three shapes of item, and the strict schema enforces them:

    hear[i]     {kind:"line", skill_ref, frame, filler, speaker:"role", translation, transliteration}
    say[i]      {kind:"line", skill_ref, frame, filler, translation, transliteration}
    ask[i]      {kind:"line", skill_ref, frame, filler, translation, transliteration}
    words[i]    {kind:"word",  skill_ref, text, translation, transliteration,
                 example, example_translation, image_api_prompt}
    chunks[i]   {kind:"chunk", skill_ref, text, translation, transliteration,
                 example, example_translation}
    numbers[i]  {kind:"number", skill_ref, frame, filler, value, translation}

…and one field that is not a shelf at all:

    dialogue[i] {turn:"role"|"you", ref:"<shelf>[<index>]"}

- `dialogue` is an ORDER over the shelves, never a seventh shelf. A `ref` names a card this
  answer already wrote — `hear[2]`, `say[0]`, `ask[1]` — and a ref that names no card of this
  day is `card.dialogue_ref_invalid`. A `role` turn refs `hear`; a `you` turn refs `say` or
  `ask`; two turns of the same side in a row are `day.dialogue_not_alternating`.

- A WORD and a CHUNK are the card themselves, so they write `text` — the term — and
  `translation` is the term's own key, not a sentence. They stand in the day's frames through
  their `example`, which is a sentence of this scene containing the term.
- A LINE (hear / say / ask) and a NUMBER are assembled: `frame` carries `___` at most once and
  `filler` is the key that goes into it — a word or a chunk of THIS day, character for
  character. A line the user says whole (a formula) has no `___` and an empty `filler`; a quoted
  interlocutor line is the same shape.
- `transliteration` is required whenever the two languages use different scripts, and is written
  in the letters of {{support_lang}} only — no punctuation, no Latin letters.
- Nothing carries a `text` field on a line: the server assembles it and would overwrite yours.

Respond with JSON only, matching the shape above exactly. No commentary, no code fences.
