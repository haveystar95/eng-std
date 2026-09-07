# plan_day.v0.8 — один день Learning Plan, сцена парами + тематические слова (P2)

> **Боевой промпт.** Реестр: `docs/prompts/REGISTRY.md`, id **P2**. Рендерится
> `PlanPromptLibrary`, вызывается `PlanDayComposer`, ответ судит `PlanDayValidator` и — по каждой
> паре отдельно — `PlanPairCourt` (промпты **P2J v0.2** / **P2P v0.2**).
> Всё, что выше первого `---`, до модели не доезжает (см. `PlanPromptLibrary::body()`).
>
> **v0.8 против v0.7 — наряд DAY-FIX-3, Ч.2.1: больше слов.** Три дня свежего плана прошлись за
> десять минут — материала мало. Слова дня теперь = слова из реплик сцены (как было) ПЛЮС 6–10
> ТЕМАТИЧЕСКИХ существительных и связок ситуации, которых в репликах нет («рецепт», «страховка»,
> «анализы» у врача). Тематические — ОТДЕЛЬНЫМ массивом `topical[]` (у каждой `kind: word | chunk`);
> первая редакция v0.8 держала булев флаг `topical` на карточках `words`/`chunks`, и два живых дня
> на стенде 07.09 показали: модель помечает куски реплик `false` и ничего не добавляет. Правило Y5
> канона GEN-1 меняется: слово либо стоит в реплике, либо пришло в `topical[]`; больше ничего — иначе
> сервер выбрасывает (`PlanDayComposer::pruneUnspoken()`); тематическое, стоящее в реплике, —
> слово реплики. Судья слов не нужен. Тематические слова тренируются как обычные слова (интро →
> перевод выбором), в диалог не входят; экран дня подписывает их «по теме».
>
> Всё остальное — v0.7 (канон качества GEN-1): полка ответов — только ответы; уровень числом;
> `speaking_keys[]` у каждой `you`; перевод ровно по смыслу; `ask`-пара — приглашение → вопрос;
> `value` числа — цифры. Дни, написанные до v0.8, не мигрируются.

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
  Never duplicate these phrases as cards. The rescue kit is where "please repeat", "slower",
  "write it down" live — the day never teaches such lines again.
- Balance (may be empty): {{balance}} — 'understanding' or 'speaking', the outcome of the user's listening check.

OUTPUT — one JSON object, nothing else:
{
  "pairs":   [ ... ],   // the conversation as EXCHANGES, in the order they happen. Guide: 4–6, never fewer than 4.
  "words":   [ ... ],   // single words: the PIECES OF THE LINES only (with chunks: guide 4–6).
  "chunks":  [ ... ],   // set combinations of 2–4 words that stand in the lines.
  "topical": [ ... ],   // the TOPICAL vocabulary of the situation that the lines do not use — words and chunks, 6–10 items, never fewer than 6. See TOPICAL CARDS.
  "numbers": [ ... ]    // numbers in live scene context, listening only. Guide: 2–4.
}

A PAIR is one exchange:
{
  "kind": "answer" | "ask",
  "role": { ... },      // what the other person says. Understanding only.
  "you":  { ... }       // what the user says RIGHT AFTER IT — a direct reply to that very line.
}
- "answer": the other person ASKS or STATES something, and "you" ANSWERS exactly that, on the
  substance. The reply must be an answer TO THIS LINE — not to some other question of the scene.
  «What kinds of projects did you work on?» is answered by a line about projects, never by «Later,
  I moved into an in-house team.» A reply that says nothing («The problem is my steak», «Oh, that's
  a bit odd then») is not an answer either.
- In an "answer" pair, "you" is NEVER a clarification, a repeat request or a counter-question:
  not «Sorry, do you mean the users?», not «What does that mean?», not «Could you say that again?»,
  not «What is the rent then?». Those are not answers. Repair moves are the rescue kit's job; a
  clarifying question, when the scene really needs one, is an "ask" pair.
- "ask": the other person INVITES a question («Anything you'd like to ask?», «Any questions about
  the flat?», «Is there anything else?»), and "you" asks one. The role line of an "ask" pair is an
  INVITATION — not a question of its own («Can I help with anything else?» is fine, «Would you like
  to move in next week?» is not) — and the "you" line is a QUESTION, ending with a question mark,
  never a request or a statement. Include at least ONE "ask" pair, and put it where a person would
  actually get the floor — usually near the end of the encounter.
- The pairs are ONE conversation — one episode from its first line to its last, each exchange
  following from the previous one — not a list of unrelated questions about the topic.
- Every "you" line is 3–8 words when assembled; every "role" line is up to 12 words.
- The role lines of the scene must differ in FUNCTION, not only in wording: a question about one
  thing, a question about another, an invitation, a statement. Two role lines that ask the same
  thing in other words («Anything else?» / «Do you have any questions?») are one line — write one
  of them.

LEVEL — HOW THE USER TALKS
- Level {{level}}. For "basic": 4–8 words, plain everyday speech, no office or textbook register —
  «I did the planning» and never «I was responsible for planning and delivery»; no words a
  beginner would not have. For "conversational" and "fluent": up to 12 words, natural spoken
  register. Before writing any "you" line ask: would a person of this level really say exactly
  this? If not, simplify it.
- "role" lines are natural speech of the other person; they may be a little richer than the
  user's, still up to 12 words.

EVERY ITEM CARRIES
- "kind": "line" for role/you, "word", "chunk", "number".
- "skill_ref": the id of ONE skill of the scene this card serves. Every card names its
  skill; every skill of the scene should get at least one card.
- "frame": the sentence with exactly one gap `___` where the key belongs. The gap exists
  ONLY in frame — never in translation, examples or anywhere else. Write the punctuation right
  after the gap with no space: «I came in for ___.», never «I came in for ___ .».
- "filler": the key — exactly the text of the card, as it stands in the gap. The server
  assembles the final sentence from frame + filler; you never output the assembled text.
- "translation": translation of the WHOLE assembled sentence into {{support_lang}}. Translate
  the finished sentence, not the frame: no `___` in it, and it must contain the translation of
  the key. EXACT MEANING ONLY: nothing added from the scene's context («reporting platform» is
  «платформа отчётности», never «платформа кредитной отчётности»), nothing dropped («still
  water» keeps «still»), nothing literal that reads as nonsense («What seems to be the problem?»
  is «Что случилось?» / «Что вас беспокоит?», not «Что кажется проблемой?»).

SHELF SPECIFICS
- role: add "speaker": "role". These lines are for understanding only and may be up to 12
  words. Make them the lines this person will really face — use opening_lines as raw
  material, adapted to the scene.
- you: 3–8 words when assembled. If a thought needs more, split it into two pairs or
  move the load into a chunk — long utterances are trained by assembly, never as cards.
  Spoken register, natural word order, one thought per line. Every "you" line also carries
  "speaking_keys": 1–2 SHORTER or SIMPLER ways to say the same reply that a learner of this
  level might produce and that still count as correct when spoken — «two years» and «about two
  years» for «I have two years of commercial experience»; each 1–6 words, in {{target_lang}},
  never identical to the assembled line and never identical to the filler alone.
- words: at most 3 words. Also carry "example" — a NEW sentence in the scene's world using
  the word (never the word alone, never equal to any line of this day), "example_translation"
  and "image_api_prompt" — a concrete photographable moment. Only words get images. The word's
  "translation" is its meaning IN THIS SCENE — «cold» in «the soup is cold» is «холодный», not
  «простуда»; «team» is «команда», not «отдел».
- chunks: 2–4 words, set combinations (like "make an appointment"). Carry "example" /
  "example_translation" as words do; no image.
  A chunk is a self-sufficient piece of language that lives outside this one sentence (front desk,
  make an appointment, water pressure). NOT a chunk: a fragment cut off from its object (works
  for), an article plus a noun (the location), or a combination of basic words (see it, for a
  week, in the morning). Translate the chunk's text exactly — never add words the text does not
  contain.
- WORDS AND CHUNKS ARE THE PIECES OF THE LINES. Every card in "words" and "chunks" must stand,
  character for character, inside at least one line of this scene (a "role" or a "you" line) —
  «hurts» in the line means the card is «hurts», not «hurt»; a chunk is written in the form it
  takes in the line («responsible for», «take a seat» — never «be responsible for», never «to take
  a seat»). Guide: 4–6 such cards, words and chunks together. A card that stands in no line is
  dropped by the server, so do not write it there — a word of the situation that the lines do not
  use belongs in "topical".

TOPICAL CARDS — the "topical" array, REQUIRED
- "topical" holds the vocabulary of THIS SITUATION that the lines do not happen to use: the
  nouns and set phrases a person SEES, HEARS and READS at this encounter — signs, documents,
  objects, roles, places, procedures. At a doctor's: «prescription», «insurance card», «waiting
  room», «blood test», «pharmacy», «symptoms», «follow-up»; at a flat viewing: «deposit», «lease»,
  «utilities»; at an airport: «boarding pass», «gate», «carry-on», «customs».
- At least 6 and up to 10 items. A day with fewer than 6 topical cards is INCOMPLETE and is
  sent back: this is the second half of the day's vocabulary and it is as important as the lines.
- Each item is a word or a chunk with "kind": "word" | "chunk", written exactly like a card of
  "words" / "chunks": "text", "translation" (the meaning in this scene), "transliteration",
  its own "example" sentence in the scene's world, "example_translation", and
  "image_api_prompt" (a concrete photographable moment for a word; "" for a chunk). Concrete,
  specific to the situation, at the user's level. The same rules as for chunks apply: no
  combination of basic words («for a week», «at the door»), no fragment cut off from its object.
- A topical card does not stand in any line and is not the filler of any line; it never
  repeats a piece of the lines, a KNOWN unit or the rescue kit. One that turns out to stand in a
  line is simply a piece of the lines — the server files it as such.
- Topical cards are trained as words and never enter the conversation.
- numbers: "frame" is a natural line the other person says that contains the number, price,
  date or address; "filler" is the number expression as spoken; add "value" — the SAME number
  as DIGITS only («20», «9:30», «2026-09-08»), never words («twice», «four eight two one» —
  such lines are not numbers cards). The user's task is listening.

CONTENT RULES
- Everything serves the scene: with today's pairs plus the rescue kit the user must be
  able to walk through this encounter.
- No basic vocabulary as cards: numbers, weekdays, family, colors, pronouns, be/have/go,
  "a little", "enough", "much", "again", "slowly", "day/days", "today" and the like are never
  cards. Numbers live only in "numbers".
- Proper names are never cards; use the scene's entities as fillers inside frames.
- For "you" lines, prefer as the key (filler) a word or chunk from today's shelves — the
  gap is where today's vocabulary plugs into living speech. Never use as the key a word the
  day must not teach (basics, numbers, proper names).
- No clones: no two cards of the day assemble into the same text; no example equals a line
  of the day; nothing duplicates the rescue kit or a KNOWN unit.
- Prefer real, specific lines over formulas; formulas must stay a minority of the day.
- If the support language requires a reading aid (see support notes), add
  "transliteration" to every role/you/word/chunk item: a practical reading of the
  assembled text in the support alphabet.
- If balance is 'understanding', make the role lines richer and the you lines shorter; if
  'speaking' — the opposite. If balance is empty, ignore this rule.
- Counts in this prompt are guidance, not law — except the minimum of four pairs and the minimum
  of six topical cards. Never pad with filler cards to hit a number.
- Answer with the JSON object only.

---

## OUTPUT SHAPE (technical, added by the code session)

    pairs[i]     {kind:"answer"|"ask", role: <role item>, you: <you item>}
    role item    {kind:"line", skill_ref, frame, filler, speaker:"role", translation, transliteration}
    you item     {kind:"line", skill_ref, frame, filler, translation, transliteration, speaking_keys:[string, string?]}
    words[i]     {kind:"word",  skill_ref, text, translation, transliteration,
                  example, example_translation, image_api_prompt}
    chunks[i]    {kind:"chunk", skill_ref, text, translation, transliteration,
                  example, example_translation}
    topical[i]   {kind:"word"|"chunk", skill_ref, text, translation, transliteration,
                  example, example_translation, image_api_prompt}
    numbers[i]   {kind:"number", skill_ref, frame, filler, value, translation}

- A PAIR is the unit. The server lays `role` of pair i onto the `hear` shelf and `you` onto `say`
  (kind `answer`) or `ask` (kind `ask`), and the conversation is the pairs in order. There is no
  `dialogue` field and no separate `hear`/`say`/`ask` arrays — writing them is a schema error.
- A WORD and a CHUNK are the card themselves, so they write `text` — the term — and
  `translation` is the term's own key, not a sentence. A card of `words` / `chunks` stands in a
  line of this scene; a card of `topical` does not have to and normally does not.
- A LINE (role / you) and a NUMBER are assembled: `frame` carries `___` at most once and
  `filler` is the key that goes into it — a word or a chunk of THIS day, character for
  character. A line the user says whole (a formula) has no `___` and an empty `filler`; a quoted
  interlocutor line is the same shape.
- `speaking_keys` is REQUIRED on every `you` item and holds 1–2 non-empty strings; the server
  refuses a `you` line that carries none.
- `topical` is a REQUIRED array of at least SIX items; the server counts a day short of them.
- `transliteration` is required whenever the two languages use different scripts, and is written
  in the letters of {{support_lang}} only — no punctuation, no Latin letters.
- Nothing carries a `text` field on a line: the server assembles it and would overwrite yours.

Respond with JSON only, matching the shape above exactly. No commentary, no code fences.
