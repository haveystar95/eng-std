## `distractors` — wrong versions of the card's example sentence

Rewrite the given example so it is **grammatically wrong in one specific way a {{source_lang}}
speaker would plausibly produce**. Each candidate changes exactly ONE thing and is otherwise
identical to the example, including its meaning.

Return **four or five candidates**, breaking four or five DIFFERENT places — not one place in five
spellings. A deterministic checker downstream throws out anything mislabelled or secretly correct,
so over-order and let it choose. If the sentence honestly breaks in fewer places, return fewer:
a candidate invented to reach the count is a sentence that is not wrong, and that is the one failure
that reaches the learner.

### The three fields around each sentence — most answers fail HERE, read it twice

- `error_span` — the wrong fragment, copied character for character out of **your own `sentence`**.
  Not out of the example, and not paraphrased. It is used to underline the mistake, so it has to be
  findable in the string you returned.
- `correction` — what that fragment should have been. Putting `correction` where `error_span` is must
  give back the given example, **word for word**. It follows that `correction` never equals its own
  span, and never carries the sentence's final `.`/`?` unless the span carries it too.
- `error_type` — exactly one of: `article`, `preposition`, `tense`, `word_order`, `false_friend`,
  `modal_to`.

Worked example (in English; the shape is the same in any language):

    example:     The post office is next to the museum.
    sentence:    The post office is next the museum.
    error_span:  "next the"        ← taken from the sentence, NOT from the example
    correction:  "next to the"     ← substituting it back reproduces the example exactly
    error_type:  "preposition"

### What counts as wrong

Read each candidate **alone**, with the example nowhere in sight, as if you had found it in a book.
If a native speaker could have written it — in any context, in any register — discard it. Fewer
distractors is a good outcome; a grammatical "distractor" marks a correct learner wrong, and that is
the most expensive thing you can produce here.

Five ways a candidate looks wrong but is not. Every one of these has reached real learners:

- **A different tense is not an error.** `tense` means a BROKEN agreement — "your workstation **are**
  ready", "I **has** been waiting". "The post office **was** next to the museum" is simply the past,
  and it is perfectly correct.
- **A swapped determiner is usually not an error.** Use `article` only where the slot is structurally
  broken — a singular countable noun with no determiner at all ("I need bank account"), an article in
  front of a plural ("a bank accounts"). Never `the` ↔ `my` ↔ `a` ↔ nothing: "I get along with the
  team" and "with my team" are both fine English, merely about different things.
- **A changed meaning is not a grammar mistake.** before ↔ after, always ↔ never, buy ↔ sell — the
  result is correct English that simply says something else, and the learner who reads it right is
  punished.
- **A typo is not a grammar mistake.** "musuem" for "museum" turns the card into a spelling hunt.
  Every word must be spelled correctly; only the grammar is broken.
- **A re-spelled contraction is not an error.** "I'd like" and "I would like", "it's" and "it is" are
  the same sentence — the grader folds them together, so such a "distractor" IS the right answer.

Never introduce a second mistake, never change the vocabulary being taught, and **never use markdown**:
no asterisks or underscores around the broken fragment. Marking it inside the sentence shows the
learner where the error is, and the card is then solved by looking for punctuation instead of by
reading the grammar.

If the card's example is missing or too short to damage in one place, return an empty list for it.
