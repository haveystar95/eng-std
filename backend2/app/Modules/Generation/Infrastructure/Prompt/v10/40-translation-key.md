## The translation is a KEY, and a key must be isomorphic to its own lock

`translation` and `example_translation` are not prose. Each is the QUESTION the learner is shown,
and the {{target_lang}} side is the ONLY answer that will be accepted. A learner who reads your
{{source_lang}} line and writes the {{target_lang}} line back must be marked right.

That makes a translation reversible or broken, and there are exactly two ways to break it. **Both
are equally damaging, they have opposite fixes, and a translation must survive both tests.**

### Wave 1 — nothing may be LOST

A translation that drops a word of `text` stops pointing at `text`. The words most often dropped are
the small ones that carry WHO is speaking and WHO is addressed, because a translation reads more
smoothly without them — and smoothness is not what this field is for.

So the translation must keep, one for one, in {{source_lang}}:

- **the addressee and the speaker** — every pronoun `text` uses for who is speaking and who is
  addressed (`us`, `me`, `you`/`your`, `we`/`our`, `them`/`their`) needs its own explicit counterpart
  on the {{source_lang}} side, in whatever form {{source_lang}} marks it. The exact word depends on
  {{source_lang}}, but the requirement itself does not;
- **every qualifier of the term** — a possessive, an adjective, a number, a preposition that changes
  the meaning;
- **the grammatical person and number** the term is in.

Worked through on the item that failed, translated into Russian:
`Tell us about a challenge you faced` came back «Расскажите о вызове, с которым вы столкнулись».
It reads well and it is wrong as a question, because the word for `us` is gone and `Tell me…`,
`Tell us…` and `Describe…` all answer it equally. The translation the learner needs keeps that word
explicit — «Расскажите **нам** о вызове, с которым вы столкнулись» — clumsier by a word, and
unambiguous. The same failure and the same fix apply in the learner's own language too, whichever it
is: whatever word that language uses for `us`, `me`, `you`, or `our` has to survive in the
translation, even at the cost of elegance.

Same failure, same fix, still in Russian: `Tell us about your experience` needs the word for `your`
kept explicit too — «Расскажите нам о **вашем** опыте», not «Расскажите о своём опыте», where
«своём» hides whose experience it is.

### Wave 2 — nothing may be ADDED

The mirror, and the one that is easier to miss because the result reads better than the correct
version. The translation must not say anything the {{target_lang}} side never said. A learner who
answers with `text` — the right answer — is then marked wrong for failing to produce a word that was
never there.

`I get along with my team` came back «Я **хорошо** лажу со своей командой». Nothing licences
«хорошо»: there is no `well`, no `good`, no `nicely` in the source. The learner writes the exact
sentence, the key says something else, and an honest answer is recorded as a lapse.

So, in the other direction:

- do not add an intensifier or an evaluation (`хорошо`, `очень`, `совсем`) that the source has no
  word for;
- do not add a person the source does not name — no `нам`/`вам`/`мне` unless `us`/`you`/`me` (or a
  first/second-person form) is actually there;
- do not add a possessive, an article-like specifier or a qualifier the source leaves open;
- do not "improve" the meaning by narrowing it: a translation that says more than the source is a
  different question.

### Both waves in one test

Before you commit to a translation, read it with the {{target_lang}} side hidden and ask two
questions in order:

1. **Is `text` the answer I would give?** If several unrelated {{target_lang}} expressions fit
   equally well, the translation is too vague — narrow it. If the item is genuinely ambiguous out of
   context, prefer a different item over a vague question.
2. **Is every word of my translation earned by something in `text`, and is every word of `text`
   answered by something in my translation?** Point at each of `us`/`me`/`you`/`your`/`we`/`our` in
   `text` in turn and find the word that carries it on the other side; then point at each meaningful
   word of the translation and find what in `text` licences it. A word with nothing on the other side
   — in either direction — is the defect.

Two more rules that follow from the same principle:

- **Never smuggle the answer in:** no {{target_lang}} words in the translation, no transliteration,
  no "(from the verb …)" that spells out the form. Disambiguate with the minimum that does the job:
  a more specific word, or a short parenthetical hint such as a register or domain marker.
- **No two items in one answer may share a `translation`.** Two identical questions with two
  different accepted answers is a card that cannot be passed by knowing the material.

**Accuracy beats fluency here, every time.** If the smooth translation and the exact one differ, ship
the exact one. A slightly stiff question the learner can answer is worth more than an elegant one
that marks a correct answer wrong.
