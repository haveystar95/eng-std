## `synonyms` — other {{target_lang}} words for the same thing

The learner is shown the {{source_lang}} meaning and asked for a {{target_lang}} word. More than one
word often answers that honestly, and this field is where those go. Zero, one or two; three is the
ceiling and is rarely needed.

A candidate has to pass **BOTH** tests below. One of them alone is not enough, and the second is the
one that is usually failed.

### Test 1 — put it in the sentence

Substitute your candidate into the card's own EXAMPLE in place of the term. The sentence must stay
natural {{target_lang}} and must still say the same thing.

    TERM: purpose · EXAMPLE: "The purpose of this meeting is to agree a date."
      goal → "The goal of this meeting is to agree a date."   natural, same meaning.   passes
      plan → "The plan of this meeting is to agree a date."   says something else.     fails
      use  → "The use of this meeting is to agree a date."    nobody speaks like that. fails

### Test 2 — answer the translation with it

Now cover the {{target_lang}} side completely and read ONLY the {{source_lang}} translation on the
card. Would a competent speaker answer that translation with your word? Read the translation as it
stands — not a narrower version of it you have in mind.

This is the test that catches what Test 1 lets through, because a NARROWER word almost always fits
the sentence:

    TERM: bank account · TRANSLATION: «банковский счёт»
      "I need a savings account to receive my salary."  — Test 1 passes: perfect English.
      But «банковский счёт» is not «сберегательный счёт». A savings account is a TYPE of bank
      account, so it answers a question that was not asked.                             fails

    TERM: credit card · TRANSLATION: «кредитная карта»
      "charge card" is a different product, not another name for this one.              fails

    TERM: withdrawal limit · TRANSLATION: «лимит снятия»
      "cash withdrawal limit" is narrower — it names one kind of withdrawal.            fails

    TERM: debit card · TRANSLATION: «дебетовая карта»
      "bank card" — a competent speaker does answer «дебетовая карта» with it.          passes

Four ways a candidate fails Test 2, all of them seen in real runs:

- a TYPE of the thing is not the thing — `savings account` does not answer «банковский счёт»;
- a BROADER word is not the thing — `automatic payment` does not answer «прямое дебетование»;
- a DIFFERENT STEP of the same process is not the thing — `obtain a prescription` does not answer
  «получить лекарство по рецепту»;
- a different PART OF SPEECH is not the thing — `decide` does not answer «решение».

Also not synonyms: a different REGISTER when the term is neutral (`pooch` for `dog`), and another
word that merely shares the term's {{source_lang}} gloss — two words can be glossed the same and
still be two different things.

### Shape

A word, or two where the term itself is two. Never longer than the term plus one word. If what you
are about to write is a definition — "the reason for doing something" — you have answered a
different question; leave the list empty instead.

**Only for a SINGLE WORD or a short lemma** — one word, or two where the term is naturally two
(`bank account`, `get up`, `check in`). For a whole phrase or a sentence, return an empty list: what
you would write there is a PARAPHRASE, not a synonym, and it is discarded downstream.

Skip anything already listed under ALREADY ACCEPTED or ALREADY SYNONYMS in the data: it is there.

These words are accepted as CORRECT ANSWERS. Run both tests before you write one — a term with a
good synonym and an empty list is a card that marks a learner wrong for knowing the language, and a
term with a narrower one is a card that teaches an equivalence that does not hold.
