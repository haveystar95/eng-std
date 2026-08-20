## `distractors` — 4–5 wrong versions of the card's example sentence

Rewrite the given example sentence so it is **grammatically wrong in one specific way that a
{{source_lang}} speaker would plausibly produce**. Each distractor changes exactly ONE thing and
stays otherwise identical to the original, including its meaning.

**Propose CANDIDATES, not a finished set.** A card needs two wrong sentences to be playable and
stores at most three, and a deterministic checker downstream throws out every one that turns out to
be secretly correct, to repeat a sibling, or to mislabel its own error. Historically about half of
them do. So: work the sentence for four or five honest candidates and let the checker choose. Asking
for exactly what fits the card is how a card ends up with one option and no question.

This is an over-order, **not a quota.** Every rule below still binds every candidate, and the
scrapping happens after you: a fifth "distractor" invented to reach five is a sentence that is not
wrong, and those are the ones that survive the checker and reach the learner as a correct answer
marked wrong. If this sentence honestly breaks in two places, return two. See the closing rule —
fewer, all real, beats five with a passenger.

The error classes below are a fixed taxonomy; the concrete mistake is yours to derive from the
{{source_lang}} → {{target_lang}} pair. Work out where the two languages disagree and let the
distractor be the {{target_lang}} sentence that results from following {{source_lang}} rules. Use
`error_type` to say which class you used:

- `article` — an article or determiner error typical of {{source_lang}} speakers: a missing, extra or
  wrong article, in the direction {{source_lang}}'s own article system (or lack of one) pushes them.
  Only where the article is actually REQUIRED or forbidden. Never on a plural or a mass noun whose
  article is optional — "I paid my bills by direct debit" and "…by the direct debit" are both things
  a native says in some context, so neither is a mistake.

  **A determiner swap is legal here only if the result is ungrammatical in EVERY context.** Read the
  changed sentence with the original nowhere in sight. If a native speaker could have written it
  about some other situation, you have not made an error — you have written a different sentence,
  and the learner who reads it correctly is punished for it. Determiners carry meaning, not just
  grammar, so exchanging one for another almost always lands on a sentence that is both grammatical
  and true, merely about something else. Three real rows from this станок, all wrong to have written:

  - "I get along with my team well" → "I get along with **the** team well" — fine English; "the team"
    is just a team already mentioned.
  - "Are utilities included in the rent?" → "Are **the** utilities included in the rent?" — fine
    English; people say both.
  - "Could you explain **the** fees…" → "Could you explain fees…" — fine English; a bare plural reads
    generically.

  So: never swap `the` ↔ `my` / `your` / `a` / no-article-at-all and call the result an error. Use
  this class only where the slot is structurally broken — a singular countable noun left with no
  determiner at all ("I need bank account"), or an article standing in front of a plural
  ("a bank accounts").
- `preposition` — a preposition chosen by literally translating the {{source_lang}} pattern instead
  of the {{target_lang}} one. It must be a preposition {{target_lang}} does not allow here, not
  merely a different one that also works.
- `tense` — a **broken agreement**, not a different tense. The error must be an ungrammatical
  combination: subject and verb that disagree ("your workstation **are** ready"), an auxiliary that
  cannot take that form ("I **has** been waiting"), a participle where a finite verb belongs.
  Changing WHEN the sentence happens is not an error: "I wanted to minimize fees" and "I want to
  minimize fees" are both perfectly grammatical, and a learner who reads one as the other has made no
  mistake. If you cannot break agreement in this sentence, do not use this class.
- `word_order` — {{source_lang}}'s word order carried into {{target_lang}} where {{target_lang}}
  requires its own. The result must be one {{target_lang}} refuses, not one that is merely less usual.
- `false_friend` — a word that looks or sounds like a {{source_lang}} word but does not mean it here
  (a false cognate), or a {{source_lang}} word's most common gloss used in a context where
  {{target_lang}} demands a different one.
- `modal_to` — a verb-complement error: the infinitive marked or unmarked contrary to
  {{target_lang}}'s rule, following what {{source_lang}} would do after a modal or similar verb.

Hard rules for every distractor:

- It must be **genuinely incorrect** in {{target_lang}}. Not clumsy, not merely less idiomatic —
  wrong. A native speaker must be able to say what the mistake is.
- It must **not** be a correct answer in disguise. Do not produce a distractor that differs from the
  original only in punctuation, capitalisation, a contraction, or an optional article — those are all
  accepted as correct and the sentence would be marked wrong for no reason.
- **Never re-spell a contraction.** "He's been running" and "He has been running" are the same
  sentence; so are "I'd like" and "I would like", "it's" and "it is", and the two apostrophe glyphs
  (`'` and `’`). The grader folds all of these together before it compares, so a "distractor" that
  differs from the example only by expanding, contracting or re-typing an apostrophe IS the correct
  answer wearing a different coat, and the card marks the right choice wrong.
- **Do not repeat yourself.** No two distractors for one card may be the same sentence, and no two
  may break the same fragment. Two options that differ from the example in the same place stop asking
  "which sentence is right" and start asking which spelling of one word was meant. This is also what
  the over-order is FOR: five candidates means five different places in the sentence, worked through
  different error classes — not one error in five spellings. If you find only one honest error in
  this sentence, return one.
- `error_span` must be the wrong fragment **copied verbatim from your own `sentence`**, character for
  character. Not from the original sentence, and not paraphrased — it is used to underline the
  mistake, so it has to be findable in the string you returned.
- `correction` is what that span should have been, and it must actually repair the sentence: putting
  the correction where the span is has to give back the given example, word for word. It follows that
  a correction identical to its own span ("has been" → "has been") is not a correction at all — it is
  the card underlining a fragment and offering the same fragment back as the fix.
- **No markdown, ever.** Do not wrap the broken fragment in asterisks or underscores — not
  `is this seat **take**?`, not `_take_`, nothing. The fragment already has a field of its own
  (`error_span`); marking it inside the sentence shows the learner where the error is, and the card
  can then be solved by looking for punctuation instead of by reading the grammar. Plain sentences.
- Never introduce a second mistake, and never change the vocabulary being taught.
- **Never change the MEANING.** Swapping a word for its opposite or its counterpart — before ↔ after,
  always ↔ never, buy ↔ sell, before signing ↔ after signing — produces a sentence that is perfectly
  grammatical and simply says something else. That is not a grammar mistake, and a learner who picks
  it is being punished for reading correctly. The distractor must be the SAME statement, said wrongly.
- If a class does not apply to this language pair or this sentence, do not force it — use another
  class, or return fewer distractors.

If a card's example is missing or too short to damage in one place, return an empty list for it.
