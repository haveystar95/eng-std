## `synonyms` — other {{target_lang}} words for the same thing

The learner is shown the {{source_lang}} meaning and asked for a {{target_lang}} word. More than one
word often answers that honestly, and this field is where those go. Zero, one or two; three is the
ceiling and is rarely needed. Only for a single word or a short lemma — for a phrase or a sentence
return an empty list, because what you would write there is a paraphrase.

A candidate has to pass **BOTH** tests. The second is the one that is usually failed.

**Test 1 — put it in the sentence.** Substitute the candidate into your own `example` in place of
`text`. It must stay natural {{target_lang}} and still say the same thing.

    text: purpose · example: "The purpose of this meeting is to agree a date."
      goal → "The goal of this meeting is to agree a date."   natural, same meaning.  passes
      plan → "The plan of this meeting is to agree a date."   says something else.    fails

**Test 2 — answer the translation with it.** Cover the {{target_lang}} side completely and read only
your own `translation`. Would a competent speaker answer THAT with your word? Read the translation as
it stands, not a narrower version of it you have in mind.

    text: bank account · translation: «банковский счёт»
      "savings account" fits the sentence perfectly — and «банковский счёт» is not «сберегательный
      счёт». A savings account is a KIND of bank account, so it answers a question nobody asked.

Four ways to fail Test 2, all seen in real runs: a TYPE of the thing (`savings account` for
«банковский счёт»), a BROADER word (`vehicle` for «машина»), a DIFFERENT STEP of the same process
(`obtain a prescription` for «получить лекарство по рецепту»), a different PART OF SPEECH (`decide`
for «решение»). Also not synonyms: a different register when the term is neutral, and another word
that merely shares your {{source_lang}} translation.

A synonym is a word or two, never longer than `text` plus one word. If what you are writing is a
definition, you have answered a different question — leave the list empty.

These are accepted as CORRECT ANSWERS. A wrong one marks a different word right; an empty list where
a good one existed marks a learner wrong for knowing the language. Run both tests and decide.

## `other_translations` — when the word really means more than one thing

`translation` is ONE reading: the everyday one, the one your `example` uses, the one the card asks.
That does not change.

This is for a word whose OTHER readings a learner will genuinely meet — `bank` is a financial
institution and it is also the side of a river. At most two, in {{source_lang}}, and never a
restatement of `translation` in different words: «счёт» and «счёт в банке» are one reading spelled
twice, not two readings.

**Return an empty list unless the word is really ambiguous.** Most words are not, and a second
"meaning" invented to fill the field teaches that a word means something it does not.

## `transliteration` — how it reads, in {{source_lang}} letters

For a learner who does not know {{target_lang}} spelling rules: `text` written the way they would
sound it out, using ONLY the letters of the {{source_lang}} alphabet.

    "cómo estás"  → «комо эстас»          "job interview" → «джоб интервью»
    "Entschuldigung" → «энтшульдигунг»    "check-in"      → «чек-ин»

- Letters of {{source_lang}} only. Not IPA, not {{target_lang}} spelling, not a mixture — one
  {{target_lang}} letter in it defeats the field for the only reader it has. No stress marks, no
  brackets, no diacritics beyond what the {{source_lang}} alphabet itself uses.
- Approximate the SOUND as a {{source_lang}} speaker would say it, not the letters. It is a hint, not
  a phonetic notation; it does not have to be exact and it must be readable at a glance.
- Spaces between words; a hyphen where `text` has one. Nothing else.
- An EMPTY string "" when the two languages share an alphabet and the item already reads the way it
  is spelled — transliterating {{target_lang}} into the same letters it is already written in would
  print the word twice.
