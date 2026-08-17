---
name: database-and-persistence
description: Schema, migrations, indexes, repositories and mappers for this PostgreSQL-backed vocabulary app. Consult this skill before writing ANY migration, adding a column or table, writing a repository or query, or changing how terms, collections, progress or reviews are stored — including "quick" schema tweaks. It encodes the canonical schema and the two rules that everything depends on: terms are globally deduplicated, and progress is keyed by (user, term), never by collection item.
---

# Persistence

PostgreSQL 17. Migrations live inside the owning module (`Infrastructure/Migration`).

## Two rules the whole product rests on

**1. Terms are global and deduplicated.** A word or phrase exists once in `terms`,
regardless of how many collections reference it. Dedup key is
`(lang, normalized_text, pos)`. Normalization: trim, collapse whitespace, casefold,
strip leading articles for phrases. Never create a term without going through
`Vocabulary`'s `FindOrCreateTerms` command.

**2. Progress is keyed by `(user_id, term_id)`.** Not by collection, not by
collection item. A user who learns "bank" in one collection has learned it everywhere.
Collection progress is computed by joining `collection_items → user_term_progress`,
never stored. If you find yourself adding `collection_id` to a progress table, stop —
that is the bug this schema exists to prevent.

## Where the schema lives

The live schema is the migrations in `app/Modules/*/Infrastructure/Migration/`. Read them
before adding a column — this file does not carry a copy of the DDL, because a copy would
silently drift and you'd end up trusting the wrong one. The initial schema design and its
reasoning are in `ARCHITECTURE.md` (Appendix B), frozen as a design record.

What this skill does carry are the invariants that must survive every future migration:

- `terms` is unique on `(lang, normalized_text, COALESCE(pos,''))`. Never add a second path
  that creates terms bypassing `Vocabulary`'s `FindOrCreateTerms`.
- `user_term_progress` is keyed `(user_id, term_id)`. No `collection_id` column, ever.
- `reviews` is append-only with a client-supplied ULID primary key. Inserts are
  `ON CONFLICT DO NOTHING`; there is no `UPDATE` on this table.
- `collection_items` is unique on `(collection_id, term_id)`.
- Collections are soft-deleted (`deleted_at`) so offline clients can receive tombstones.
- Statistics tables are projections: every one of them must be rebuildable from `reviews`
  by a replay command. If you add a stat that can't be recomputed, it's stored wrong.

## Indexes that must exist

These back the hot paths; if a migration touches these tables, re-check them.

```sql
CREATE INDEX ON user_term_progress (user_id, due_at) WHERE state <> 'new';  -- session start
CREATE INDEX ON collection_items (collection_id, position);
CREATE INDEX ON collection_items (term_id);          -- "which collections contain this term"
CREATE INDEX ON reviews (user_id, answered_at DESC);
CREATE INDEX ON collections (owner_id) WHERE deleted_at IS NULL;
CREATE INDEX ON collections (type, visibility) WHERE deleted_at IS NULL;
CREATE INDEX ON terms USING hnsw (embedding vector_cosine_ops);   -- near-duplicate detection
```

The due-cards query (`user_id`, `due_at <= now()`, ordered, limited) runs at every session
start on mobile. Any change near `user_term_progress` needs an `EXPLAIN` check.

## Migration conventions

- One concern per migration; file name states intent (`add_embedding_to_terms_table`).
- Always reversible, or explicitly `down()` with a comment saying why not.
- ULIDs stored as `char(26)`; no auto-increment primary keys anywhere.
- Timestamps are `timestamptz`, always UTC. Client sends local time only as a display hint.
- Adding a column to a hot table: nullable or with a default that doesn't rewrite the table;
  backfill in a separate job, then add the constraint.
- Enum-like columns: `text` + `CHECK`, not PG enums (cheaper to evolve). The PHP side is a
  backed enum in `Domain/ValueObject`.
- Money/scores: `numeric`, never float.

## Repositories

```php
final class EloquentCollectionRepository implements CollectionRepository
{
    public function __construct(private CollectionMapper $mapper) {}

    public function getById(CollectionId $id): Collection
    {
        $model = CollectionModel::with('items')->find($id->value)
            ?? throw new CollectionNotFound($id);

        return $this->mapper->toDomain($model);
    }

    public function save(Collection $collection): void
    {
        DB::transaction(function () use ($collection) {
            $state = $this->mapper->toPersistence($collection);
            CollectionModel::updateOrCreate(['id' => $state['id']], $state['attributes']);
            // items: diff and upsert, then delete removed ones
        });
    }
}
```

- Repositories return **entities**, never Eloquent models. Models never leave `Infrastructure`.
- Repositories are for aggregate writes and by-id reads. **Read queries for the API live
  in Query handlers** and may use the query builder directly against projections — don't
  force list/statistics endpoints through aggregates, that's how N+1 and slow endpoints appear.
- No repository method named `findByFilters(array $filters)`. Explicit methods, explicit types.

## Concurrency

- Wrap multi-row aggregate writes in `DB::transaction`, keep transactions short.
- `reviews` inserts are idempotent by PK — on conflict do nothing, never update.
- `user_term_progress` updates from concurrent devices: `SELECT ... FOR UPDATE` on the
  `(user_id, term_id)` row inside the transaction, and apply reviews in `answered_at` order.
- `items_count` on `collections` is denormalized: update it in the same transaction as the
  item change, and add a reconcile command for drift.

## Before finishing a persistence task

1. Does the change respect the two rules at the top?
2. Are the indexes for the new access path created in the same migration?
3. Rollback tested locally **on the disposable test database** — never on `wordtrainer`:
   `docker compose exec -T -e DB_DATABASE=wordtrainer_test app php artisan migrate:fresh`
   then `... -e DB_DATABASE=wordtrainer_test app php artisan migrate:rollback`.
   A bare `migrate:fresh` drops the dev data (it did, on 2026-08-14) and the app now refuses it.
   The dev database only ever moves forward: plain `migrate`.
4. Is a read model involved, and if so is it rebuildable from `reviews`?
