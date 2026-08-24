# Vocabulary

**Owns:** terms (word or phrase), translations, **synonyms**, examples, **descriptions**, audio, dedup

## Examples and their glosses (`term_examples` + `example_translations`)

An example is a sentence USING the term, so `term_examples.lang` is the term's language — a fact,
not a choice. Its GLOSS is written in a support language and lives one table down, one row per
`(example, lang)`, the same shape `term_translations` has always had. Readers pick the row by the
learner's language through `ExampleTranslationPick` (asked-for language, then any, then `id`) — the
sibling of `TranslationPick`, with the same explicit fallback so a card never blanks.

It used to be one column, `sentence_translation`, with no language at all: whatever language the
collection that first pulled the term in happened to support. A term translated into ru AND uk had
exactly one gloss and nothing said whose it was (DECISIONS п. 138). The column is retained, marked
DEPRECATED, unread and unwritten — `tests/Feature/Vocabulary/ExampleTranslationTableTest.php` runs
every writing path and asserts it stays NULL — and it is dropped in its own migration once phase A
of the multilanguage move has landed.

## Descriptions (`term_descriptions`)

What a word MEANS, written in the language being learned — one or two A2–B1 sentences that never
contain the word itself. Its own table for the reason translations and examples have one: it is
written in A LANGUAGE, and a column on `terms` would have to pick one forever. One row per
(term, lang), write-once (`TermDescriptionWriter::ensure` never overwrites: a term outlives the
lookup that created it, and the second learner's cheaper model must not churn the first one's).

Written today by exactly one path — the search lookup (`POST /search/add`). The store catalogue has
no descriptions and is **not** being backfilled: that is a paid станок run over the whole showcase.
The trainer that needs one refuses a term that has none, by content, like every other trainer.

## Synonyms (`term_synonyms`) — and why translations needed no table

A SYNONYM is another word on the STUDIED side with nearly the same meaning: `purpose` → `goal`,
`aim`. Its own table, one row per word (never one string with a separator), unique on
`(term_id, text)`, carrying the term's language and a `source` of `auto` | `curated` so a re-run of
the станок cannot overwrite what a person pinned.

It is deliberately NOT `term_accepted_variants`. A variant is another SPELLING of the same word
(`organise`/`organize`) and is accepted wherever the word is typed; a synonym is another WORD and
answers only a card that asked what the term means. One table would make that difference
unrepresentable and the looser reading would win — a learner typing `goal` into a listening card
would be marked right for a word they never heard. The grader keeps them apart through
`ExerciseMode::acceptsSynonyms()`.

**Several translations of one term into one language have always been legal**, and no schema change
was needed for them: `term_translations_uidx` is unique on `(term_id, lang, text)` — not on
`(term_id, lang)` — so the rows simply accumulate, with exactly one of them per language pinned by
`is_primary` (`Term::addTranslation`, the A7 rule). Eighteen of the 705 live `(term, lang)` pairs
already hold more than one. `TranslationPick` decides which is the QUESTION; the rest are
alternatives, and `TermContentView::$translations` is where readers see them.

**A primary is written once and never re-decided by a machine.** A translation arriving marked
primary for a language that already has one is stored as an alternative, not promoted:
re-generation, a dedup merge and a second lookup of the same word must not change the question a
learner is already being asked. Moving the pin takes `Term::pinTranslation()`, which only the two
authorities in the trust hierarchy call — the learner (a translation they were shown and confirmed)
and a curator.

## Search (`TermSearchReader`)

Exact + prefix over `terms.normalized_text` and the learner-language rows of `term_translations`.
Deliberately not fuzzy — see the Generation README for why the free half of search lives beside the
paid one.

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http).

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/` for the rules.
Boundaries enforced by `deptrac.yaml`.
