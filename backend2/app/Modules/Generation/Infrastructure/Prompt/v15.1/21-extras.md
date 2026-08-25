## `synonyms` — an empty list is the normal answer

The learner is shown the {{source_lang}} meaning and asked for a {{target_lang}} word. Sometimes a
SECOND word answers that question exactly as well, and refusing it would mark a learner wrong for
knowing the language. That is what this field is for, and it is the whole of what it is for.

**The default is an empty list, and most items keep it. If you hesitate over a candidate, do not
write it.** The hesitation IS the judgement: what you are hesitating about is a difference — in
meaning, in strength, or in register — and any of the three disqualifies. An empty list costs
nothing. A wrong entry costs more than nothing, because what goes here is not a note beside the card:
it is accepted as a CORRECT ANSWER, so a wrong one teaches the learner that a different word is this
word.

Only for a single word or a short lemma. Zero is the usual answer, one is a good day, and three is a
ceiling that should almost never be reached.

A candidate is written only if it passes **ALL THREE** tests. Most candidates fail one of them.

**Test 1 — put it in the sentence.** Substitute the candidate into your own `example` in place of
`text`. It must stay natural {{target_lang}} and still say the same thing — not something close, the
same thing.

    text: purpose · example: "The purpose of this meeting is to agree a date."
      goal → "The goal of this meeting is to agree a date."   natural, same meaning.  passes
      plan → "The plan of this meeting is to agree a date."   says something else.    fails

**Test 2 — answer the translation with it.** Cover the {{target_lang}} side completely and read only
your own `translation`. Would a competent speaker answer THAT with your word? Read the translation as
it stands, not a narrower or a wider version of it you have in mind.

    text: bank account · translation: «банковский счёт»
      "savings account" fits the sentence perfectly — and «банковский счёт» is not «сберегательный
      счёт». A savings account is a KIND of bank account, so it answers a question nobody asked.

**Test 3 — same strength, same colour.** Turn the pair around: would you translate the CANDIDATE
back with the same `translation`, or with a different {{source_lang}} word? A word that is stronger,
weaker, warmer, drier, more formal or more colloquial than the term is a different answer to the
card, even where a dictionary lists the two together.

    text: surprised · translation: «удивлённый»
      "amazed" is «поражённый» — the same family, one notch stronger. A different word.
    text: jealous · translation: «ревнивый»
      "envious" is «завистливый». Neighbouring feelings, and the card asks for one of them.

Seven ways a candidate fails, every one of them written by a real run of this prompt: a TYPE of the
thing (`savings account` for «банковский счёт», `caring` for «добрый»), a BROADER word (`nice` for
«добрый», `happy` for «жизнерадостный», `vehicle` for «машина»), a NEIGHBOURING but different state
(`envious` for «ревнивый»), a STRONGER or a weaker word (`amazed` for «удивлённый»), ANOTHER SENSE
of the candidate that the learner meets first (`bright` for «жизнерадостный» — it reads as «умный»
or «яркий»), a DIFFERENT STEP of the same process (`obtain a prescription` for «получить лекарство
по рецепту»), a different PART OF SPEECH (`decide` for «решение»). Also not synonyms: a word that
merely shares your {{source_lang}} translation, and a word carrying a register the term does not
have.

**A phrase, a phrasal verb or any other multi-word term: an empty list, unless the WHOLE expression
has an equivalent.** Not the verb on its own, not the head noun — the whole thing, substitutable in
your own example with the meaning untouched. A phrasal verb with a fan of senses (`take off`, `stand
out`, `get by`) has no such twin, and the expected answer for one is empty; a paraphrase of it is a
definition wearing a synonym's clothes.

A synonym is a word or two, never longer than `text` plus one word. If what you are writing is a
definition, you have answered a different question — leave the list empty.

These are accepted as CORRECT ANSWERS. A wrong one marks a different word right; an empty list where
a good one existed marks a learner wrong for knowing the language. The two costs are not equal —
that is why the default is empty. Run all three tests and decide.

## `other_translations` — a genuinely DIFFERENT meaning, or nothing

`translation` is ONE reading: the everyday one, the one your `example` uses, the one the card asks.
That does not change, and this field does not compete with it.

**The default is an empty list, and the rule above holds here too: if you hesitate, write nothing.**
This is for a word a learner will genuinely meet in a second, unrelated MEANING — `bank` is a
financial institution and it is also the side of a river. At most two, in {{source_lang}}.

Three things that look like other meanings and are not:

- **The same meaning worded differently.** «счёт» and «счёт в банке» are one reading spelled twice.
  A rewording, a near-synonym of your own translation, a longer or a shorter phrasing of it — one
  reading each time. This field is not where a translation gets refined.
- **A reading that belongs to another PART OF SPEECH than the card.** `upset` as an adjective is
  «расстроенный»; «опрокинутый» and «нарушенный» are the verb, and the card is about the adjective.
  Read your own `text` and `example` first: whatever they are, the other readings have to be that too.
- **A shade of the same meaning in another context.** One word used about a person and about a place
  is one meaning applied twice.

A second "meaning" invented to fill the field teaches that a word means something it does not.

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
