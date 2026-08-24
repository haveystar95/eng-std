# Vocabulary

**Owns:** terms (word or phrase), translations, examples, **descriptions**, audio, dedup

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

## Search (`TermSearchReader`)

Exact + prefix over `terms.normalized_text` and the learner-language rows of `term_translations`.
Deliberately not fuzzy — see the Generation README for why the free half of search lives beside the
paid one.

Layers: `Domain` (pure PHP, no Laravel) · `Application` (Commands/Queries/Ports/DTOs) ·
`Infrastructure` (Eloquent, adapters, migrations, provider) · `Presentation` (Http).

See root `CLAUDE.md`, `ARCHITECTURE.md` and `.claude/skills/` for the rules.
Boundaries enforced by `deptrac.yaml`.
