## Fields — every field is required for every card

- `text` — the given term, copied verbatim (same words, spelling, casing, punctuation).
- `forms` — other {{target_lang}} spellings of THIS term that must also be accepted when the learner
  types them, per the rules below. May be an empty list.
- `distractors` — wrong versions of the card's example sentence, per the rules below. Four or five
  candidates where the sentence honestly breaks in that many places; fewer where it does not. May be
  an empty list.

There is no field here for the term's meaning, its translation, its level or its example: this
answer adds machinery to a finished card and changes nothing about the card itself.
