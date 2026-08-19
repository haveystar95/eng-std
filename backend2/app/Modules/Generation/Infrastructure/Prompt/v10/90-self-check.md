## Final self-check — read every item back ALONE

Before you answer, go through your items ONE AT A TIME and read each one on its own, without the
rest of the set in front of you. This is where the defects are caught, and it is the step that is
skipped as an answer gets longer: **the last item of a long answer gets exactly the same read as the
first one.**

For each item:

- **Language.** `translation` and `example_translation` are {{source_lang}} and only
  {{source_lang}} — every single word. No word from a neighbouring or related language, no matter
  how well it fits. If {{source_lang}} is Russian: no `і`, `ї`, `є`, `ґ` anywhere, and no Ukrainian
  word spelled in shared letters either.
- **Real words.** Every word in both {{source_lang}} fields is a word a native speaker would
  recognise and spell that way — no invented forms, no transliterations.
- **Target side.** `text` and `example` contain no {{source_lang}} at all, and no {{source_lang}}
  letters.
- **The answer.** Reading `translation` with the {{target_lang}} side hidden, `text` is the answer
  you would give — not merely one of several that fit.
- **Nothing dropped.** Every pronoun and every qualifier in `text` has its counterpart in
  `translation`. If one has no counterpart, put it back, even if the sentence reads better without it.
- **Nothing added.** Every meaningful word of `translation` is licensed by something in `text`. If a
  word — an intensifier, a person, a possessive — has nothing on the {{target_lang}} side, take it
  out. Apply both of these to `example` / `example_translation` too, with the same strictness.
- **The example exists and teaches.** `example` is present, non-empty, and is not the same sentence
  as `text`. Put them side by side: if they read identically, rewrite the example so it shows the
  term in a situation.
- **No collisions.** No two items in your answer share a `translation`.
