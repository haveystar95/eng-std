# Vocabulary

**Owns:** terms (word or phrase), translations, examples, **descriptions**, audio, dedup

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
