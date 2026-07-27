# WordTrainer Backend — working agreement

Vocabulary trainer. Flutter/iOS client + Laravel API. One developer, one deploy, one paradigm.

## Read first

- `ARCHITECTURE.md` — the design and the reasoning behind it
- `skills/laravel-modular-ddd/SKILL.md` — **the paradigm; applies to every backend change**

Then, depending on the task:

| Task | Skill |
|---|---|
| any backend PHP | `laravel-modular-ddd` |
| tables, migrations, repositories, queries | `database-and-persistence` |
| routes, controllers, responses | `api-endpoint` |
| progress, scheduling, sessions, stats | `learning-srs` |
| anything involving the LLM | `ai-collection-generation` |
| anything the Flutter app consumes | `mobile-sync-contract` |
| tests, CI | `testing-pest` |

## Modules

| Module | Owns |
|---|---|
| `Shared` | kernel: ValueObjects, `DomainEvent`, `Clock`, ULID generation, `Result` |
| `Identity` | users, auth tokens, devices, user settings |
| `Vocabulary` | terms (word or phrase), translations, examples, audio, dedup |
| `Collections` | collections (system/shared/custom), items, subscriptions, forks |
| `Learning` | progress, SRS scheduling, sessions, reviews, statistics |
| `Generation` | AI collection generation: requests, prompts, quotas, cost |

Details per module: `app/Modules/<Context>/README.md`. Boundaries: `deptrac.yaml`.

## Where information lives (don't duplicate it)

| Question | Answer lives in |
|---|---|
| What modules exist, what they own | this file + each module's `README.md` |
| How code must be structured | `skills/` (rules only, no inventories) |
| What endpoints exist | `openapi/openapi.yaml` + route files |
| What the schema is | migrations |
| Why it was designed this way | `ARCHITECTURE.md` |

When adding a module: create the four layers + ServiceProvider, add a `deptrac.yaml`
ruleset entry, add a row to the table above, and write the module `README.md` from
`docs/module-README.template.md`. Skills are not touched — they describe rules, not contents.

## Stack

PHP 8.4 · Laravel 12 · PostgreSQL 17 (+pgvector) · Redis/Horizon · Sanctum · Pest 3 ·
PHPStan level 8 · Deptrac · OpenAPI 3.1 → generated Dart client.

## Non-negotiables (short version)

- `app/Modules/{Shared,Identity,Vocabulary,Collections,Learning,Generation}`, four layers each.
- `Domain/` imports nothing from Laravel. Cross-module calls go through `Application` only.
- Commands mutate and return ids; Queries read and return DTOs. Controllers translate, nothing more.
- ULIDs everywhere; clients may generate ids for reviews and custom collections.
- Terms are globally deduplicated. Progress is keyed by `(user_id, term_id)`, never by collection.
- Reviews are append-only; statistics are projections that can be rebuilt.
- LLM calls are async, behind a port, with versioned prompt files and validated JSON output.
- `declare(strict_types=1)`, `final` by default, no facades outside Infrastructure/Presentation.

## Definition of done

1. Code sits in the right module and layer.
2. Domain rules have unit tests that don't touch the database.
3. `composer arch && composer stan && composer test` pass.
4. OpenAPI updated if the HTTP surface changed.
5. Migration is reversible and indexed for its access path.

## Working style

- Ask before introducing a new module, a new external dependency, or a second way to do
  something that already has a way.
- Prefer changing the design over adding a workaround. If a rule in these skills is
  getting in the way repeatedly, say so — the rule may be wrong, but it changes in the
  skill file, not silently in one commit.
