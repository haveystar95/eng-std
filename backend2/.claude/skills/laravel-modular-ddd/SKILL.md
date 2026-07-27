---
name: laravel-modular-ddd
description: The architectural paradigm for this Laravel backend — modular monolith with pragmatic DDD, four layers per module, CQRS-lite commands and queries, and strict dependency rules. Consult this skill whenever writing or reviewing ANY backend PHP code in this repo: new features, refactors, bug fixes, deciding where a class belongs, naming things, or answering "how should I structure X". Use it even when the request sounds simple ("just add a field", "quick endpoint") — the point of the paradigm is that there are no exceptions to it.
---

# Modular Monolith + Pragmatic DDD

This repo has exactly one way to structure backend code. Follow it even for small
changes; consistency is the entire value.

## Modules

Modules live in `app/Modules/`. The authoritative list of modules and what each owns is
in `CLAUDE.md`; each module documents its own scope in `app/Modules/<Context>/README.md`.
Read those instead of assuming — this file deliberately does not duplicate them, because
a list here would drift out of date and become a second, wrong source of truth.

Deciding where a new concept goes:

- Does an existing module already own this vocabulary? → new aggregate inside it.
- Does it have its own lifecycle and persistence, and would it plausibly survive being
  extracted into a separate service one day? → possibly a new module.
- Is it a technical concern (caching, notifications, media storage, search)? → not a
  module. Put it in `Infrastructure/` of the module that needs it, or as a `Shared` adapter
  if two modules genuinely need the same thing.

New modules are rare; new aggregates inside a module are normal. Before creating one,
state the reasoning and get agreement — module boundaries are expensive to move later.

## Layers inside a module

```
Modules/<Context>/
├── Domain/
│   ├── Entity/          aggregates + entities (pure PHP, no Eloquent, no facades)
│   ├── ValueObject/     TermText, LanguageCode, Grade, ReviewInterval…
│   ├── Service/         domain services (Sm2Scheduler, TermNormalizer)
│   ├── Repository/      INTERFACES only (TermRepository, CollectionRepository)
│   ├── Event/           TermCreated, CollectionGenerated…
│   └── Exception/       DomainException subclasses
├── Application/
│   ├── Command/         DTO + Handler pairs (writes)
│   ├── Query/           DTO + Handler pairs (reads, return read-model DTOs)
│   ├── Port/            interfaces for the outside world (CollectionGeneratorPort)
│   └── Dto/             input/output DTOs crossing the layer boundary
├── Infrastructure/
│   ├── Eloquent/        Models, EloquentXRepository, Mappers
│   ├── Adapter/         HTTP clients, AI providers, storage
│   ├── Migration/       module-scoped migrations
│   └── Provider/        the module's ServiceProvider (binds interfaces → impls)
└── Presentation/
    └── Http/            Controller, Request, Resource, routes.php, Policy
```

## The dependency rule

```
Domain          → nothing (not even Illuminate\*)
Application     → Domain
Infrastructure  → Application + Domain
Presentation    → Application + Domain
```

Cross-module calls go **only** through the other module's `Application` layer
(dispatch its Command or ask its Query) or through domain events. Never import another
module's Eloquent model, never join across module tables in a query, never call another
module's repository.

Deptrac enforces this (`deptrac.yaml`); run `composer arch` before claiming a task done.

## Rich vs thin — how to decide

Ceremony is a cost. Pay it only where rules live.

- **Rich domain model** (pure entity + mapper + repository interface): `Learning`,
  `Vocabulary`, `Collections`. These have invariants — scheduling math, dedup,
  ownership and visibility rules.
- **Thin/Laravel-native** (Eloquent + Action classes): `Identity`. Sanctum's `User`
  stays an Eloquent model. Don't build an anti-corruption layer around your own auth.

When adding to a rich module, write the entity first and let persistence follow.
When unsure, ask: "does this have a rule that could be violated?" If no, keep it thin.

## CQRS-lite

Writes and reads are separate paths — same database, different models.

- **Command**: mutates state, returns `void` or an identifier. Never returns view data.
- **Query**: never mutates. Free to bypass the domain and read optimized projections
  directly with the query builder — reads don't need aggregates. Return DTOs, never
  Eloquent models, never arrays with untyped keys.

```php
final readonly class AddTermToCollection            // Application/Command
{
    public function __construct(
        public CollectionId $collectionId,
        public TermId $termId,
        public UserId $actorId,
    ) {}
}

final readonly class AddTermToCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,   // Domain interface
        private TransactionManager $tx,
    ) {}

    public function __invoke(AddTermToCollection $command): void
    {
        $collection = $this->collections->getById($command->collectionId);
        $collection->assertEditableBy($command->actorId);   // rule lives in the entity
        $collection->addTerm($command->termId);             // invariant: no duplicates

        $this->tx->run(fn () => $this->collections->save($collection));
    }
}
```

## Naming

| Thing | Pattern | Example |
|---|---|---|
| Command | imperative verb | `GenerateCollection`, `SubmitReview` |
| Query | question | `GetDueTerms`, `ListUserCollections` |
| Handler | `<Name>Handler` | `SubmitReviewHandler` |
| Repository interface | `<Aggregate>Repository` | `CollectionRepository` |
| Implementation | `Eloquent<Interface>` | `EloquentCollectionRepository` |
| Port | `<Purpose>Port` | `CollectionGeneratorPort` |
| Domain event | past tense | `ReviewSubmitted`, `CollectionGenerated` |
| Read DTO | `<Name>View` / `<Name>Dto` | `CollectionProgressView` |

## Non-negotiables

- `declare(strict_types=1);` in every file. `final` by default. `readonly` for DTOs/VOs.
- No facades, no `now()`, no `auth()` outside `Presentation` and `Infrastructure`.
  Domain gets time from an injected `Clock`, identity from an explicit `UserId` argument.
- No primitive obsession at boundaries: `LanguageCode`, `TermId`, `Grade` are VOs.
  Validate in the VO constructor so an invalid value cannot exist.
- Identifiers are **ULIDs generated in the domain** (or accepted from the client for
  idempotency), never DB auto-increment. See `database-and-persistence`.
- One class per file, one public reason to change.
- Business rules never live in controllers, jobs, or Eloquent models. Jobs and
  controllers only translate input and dispatch a Command/Query.

## Checklist before finishing any backend task

1. Does each new class sit in the right module and layer?
2. Does `Domain/` import anything from Laravel? (It must not.)
3. Is the cross-module call going through `Application`?
4. Are there unit tests for the rules, without touching the DB?
5. `composer arch && composer test && composer stan` (PHPStan level 8) pass?

## Related skills

- Adding tables/migrations/repositories → `database-and-persistence`
- Adding an HTTP endpoint → `api-endpoint`
- Scheduling, progress, statistics → `learning-srs`
- Anything AI-generated → `ai-collection-generation`
- Sync/offline behaviour for the Flutter client → `mobile-sync-contract`
- Writing tests → `testing-pest`

Detailed file-by-file layout of a module: `references/module-layout.md`.
