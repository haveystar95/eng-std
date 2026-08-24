## Fields — every field is required for every card

- `text` — the given term, copied verbatim (same words, spelling, casing, punctuation).
- `distractors` — wrong versions of the card's example sentence, per the rules below.
- `forms` — other {{target_lang}} SPELLINGS of this same term that must also be accepted when the
  learner types it: regional spellings ("organise"/"organize"), one-word/two-word/hyphenated variants
  of one compound ("check-in"/"check in"). Not synonyms — those have their own field below — not
  related words, not plurals. Almost every term has none: **return an empty list unless you are
  certain.**
- `synonyms` — other {{target_lang}} WORDS that mean nearly the same as the term, per the rules
  below. Empty for anything longer than a short lemma.

There is no field here for the term's meaning, its translation, its level or its example: this
answer adds machinery to a finished card and changes nothing about the card itself.
