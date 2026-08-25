## `synonyms` — other {{target_lang}} words for the same thing

The learner is shown the {{source_lang}} meaning and asked for a {{target_lang}} word. More than one
word usually answers that honestly, and this field is where those go. **Most single words and short
compounds have one or two. Give them.**

**The test, and it is one test you can actually run.** Put your candidate into the card's own
EXAMPLE sentence in place of the term. The sentence must stay natural {{target_lang}} and must still
say the same thing.

    TERM: purpose · EXAMPLE: "The purpose of this meeting is to agree a date."
      goal → "The goal of this meeting is to agree a date."   natural, same meaning.   YES
      aim  → "The aim of this meeting is to agree a date."    natural, same meaning.   YES
      plan → "The plan of this meeting is to agree a date."   says something else.     NO

    TERM: debit card · EXAMPLE: "I prefer using my debit card for everyday purchases."
      bank card → "…using my bank card for everyday purchases."   YES

    TERM: withdraw cash · EXAMPLE: "I need to withdraw cash from the machine."
      take out cash → "I need to take out cash from the machine."  YES

Write one or two. Three is the ceiling and it is rarely needed.

**Length.** A word, or two where the term itself is two. Never longer than the term plus one word.
If what you are about to write is a definition — "the reason for doing something" — you have
answered a different question; leave the list empty instead.

**Only for a SINGLE WORD or a short lemma** — one word, or two where the term is naturally two
(`bank account`, `get up`, `check in`). For a whole phrase or a sentence, return an empty list: what
you would write there is a PARAPHRASE, not a synonym, and it is discarded downstream.

Four things that are not synonyms, each of which has reached a real learner:

- a BROADER or NARROWER word — `vehicle` is not `car`, and `sedan` is not either;
- a different PART OF SPEECH — `decide` is not `decision`;
- a different REGISTER when the term is neutral — `pooch` is not `dog`;
- another word that merely shares the term's {{source_lang}} translation — two words can be glossed
  the same and still be two different things. The substitution test above is what separates them.

Skip anything already listed under ALREADY ACCEPTED or ALREADY SYNONYMS in the data: it is there.

These words are accepted as CORRECT ANSWERS, so one that fails the substitution test marks a
different word right. When the test does not pass cleanly, leave the list empty — but do run it
before you decide, because a term with a good synonym and an empty list is a card that marks a
learner wrong for knowing the language.
