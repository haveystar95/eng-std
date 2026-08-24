## `synonyms` — other {{target_lang}} words that mean nearly the same thing

Zero to three, and zero is a perfectly good answer.

**The test, and it is one test, not a feeling.** Put your candidate into the card's own EXAMPLE
sentence in place of the term. The sentence must stay natural {{target_lang}} and must still say the
same thing. If it changes what the sentence means, or if a native speaker would not phrase it that
way, it is not a synonym for this card.

    TERM: purpose · EXAMPLE: "The purpose of this meeting is to agree a date."
    goal  → "The goal of this meeting is to agree a date."  — natural, same meaning. YES.
    aim   → "The aim of this meeting is to agree a date."   — natural, same meaning. YES.
    use   → "The use of this meeting is to agree a date."   — not how anyone speaks. NO.
    plan  → "The plan of this meeting is to agree a date."  — different thing entirely. NO.

**Only for a SINGLE WORD or a short lemma** — one word, or two where the term is naturally two
(`get up`, `check in`). For a whole phrase or a sentence, return an empty list: what you would write
there is a PARAPHRASE, and a paraphrase is not a synonym. This is checked downstream and a
paraphrase is discarded, so it costs you output and buys nothing.

Each synonym is a word or two, never longer than the term plus one word. If what you want to write
is a definition — "the reason for doing something" — you have answered a different question; leave
the list empty instead.

Do **not** include:

- a BROADER or NARROWER word: `vehicle` is not a synonym of `car`, `sedan` is not either;
- a different PART OF SPEECH: `decide` is not a synonym of `decision`;
- a word from a different register when the term is neutral: `pooch` is not a synonym of `dog`;
- another {{target_lang}} word that merely shares the term's {{source_lang}} translation — two words
  can be glossed the same and still be two different things;
- anything already listed under ALREADY ACCEPTED or ALREADY SYNONYMS in the data: it is there.

These words are ACCEPTED AS CORRECT ANSWERS when the learner is asked what the term means, so a
wrong one marks a different word right. Fewer is better than looser.
