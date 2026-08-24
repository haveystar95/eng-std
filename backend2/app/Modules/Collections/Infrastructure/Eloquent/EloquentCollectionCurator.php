<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\CollectionImpact;
use App\Modules\Collections\Application\Port\CollectionCurator;
use App\Modules\Collections\Domain\ValueObject\LanguagePair;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

/**
 * Curation writes over the Collections tables.
 *
 * Every write bumps `collections.updated_at` — including membership changes, which touch only
 * `collection_items`. The sync feed reads a subscribed collection's effective timestamp off the
 * collection row, so an item added without bumping the parent is an item a subscriber's phone
 * never asks for.
 */
final class EloquentCollectionCurator implements CollectionCurator
{
    public function impact(CollectionId $collectionId): ?CollectionImpact
    {
        $c = DB::table('collections')
            ->where('id', $collectionId->value)
            ->whereNull('deleted_at')
            ->first(['id', 'title', 'type', 'owner_id']);
        if ($c === null) {
            return null;
        }

        $termIds = DB::table('collection_items')
            ->where('collection_id', $collectionId->value)
            ->whereNull('deleted_at')
            ->select('term_id');

        return new CollectionImpact(
            collectionId: (string) $c->id,
            title: (string) $c->title,
            type: (string) $c->type,
            ownerId: $c->owner_id !== null ? (string) $c->owner_id : null,
            termsCount: (clone $termIds)->count(),
            subscribers: DB::table('user_collections')
                ->where('collection_id', $collectionId->value)
                ->whereNull('unsubscribed_at')
                ->count(),
            // Progress is keyed by (user, term) and shared across collections, so this is "learners
            // who have studied something that lives here", not "progress that would be lost".
            learnersWithProgress: DB::table('user_term_progress')
                ->whereIn('term_id', $termIds)
                ->distinct()
                ->count('user_id'),
        );
    }

    public function updateDetails(CollectionId $collectionId, ?string $title, ?string $description): bool
    {
        $changes = ['updated_at' => now()];
        if ($title !== null) {
            $changes['title'] = $title;
        }
        if ($description !== null) {
            $changes['description'] = $description !== '' ? $description : null;
        }

        return DB::table('collections')
            ->where('id', $collectionId->value)
            ->whereNull('deleted_at')
            ->update($changes) > 0;
    }

    /**
     * The one writer that does not go through the aggregate — the back-office curator writes
     * `collection_items` with SQL, for the same reason the rest of this class does.
     *
     * It still asks the SAME rule. {@see LanguagePair} is a Domain value object with no persistence
     * of its own precisely so that a writer holding two rows instead of an aggregate can construct
     * it and get the identical answer. Re-implementing «target_lang = terms.lang» here as a WHERE
     * clause would be a second copy of the invariant, and a silent one: a curator's add would come
     * back «not found» for a word that exists.
     */
    public function addTerm(CollectionId $collectionId, TermId $termId): bool
    {
        return DB::transaction(function () use ($collectionId, $termId): bool {
            $collection = DB::table('collections')
                ->where('id', $collectionId->value)->whereNull('deleted_at')
                ->first(['source_lang', 'target_lang']);
            $termLang = DB::table('terms')
                ->where('id', $termId->value)->whereNull('deleted_at')->value('lang');
            if ($collection === null || $termLang === null) {
                return false;
            }

            (new LanguagePair(
                new LanguageCode((string) $collection->target_lang),
                new LanguageCode((string) $collection->source_lang),
            ))->assertAccepts($collectionId, new LanguageCode((string) $termLang));

            $existing = DB::table('collection_items')
                ->where('collection_id', $collectionId->value)
                ->where('term_id', $termId->value)
                ->first(['id', 'deleted_at']);

            if ($existing !== null && $existing->deleted_at === null) {
                return true; // already there — adding twice is a no-op, not an error
            }

            if ($existing !== null) {
                // Previously removed: revive the same row so its id (and any client mirror of it)
                // stays stable.
                DB::table('collection_items')->where('id', $existing->id)->update([
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('collection_items')->insert([
                    'id' => Ulid::generate(),
                    'collection_id' => $collectionId->value,
                    'term_id' => $termId->value,
                    'position' => $this->nextPosition($collectionId),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->refreshCount($collectionId);

            return true;
        });
    }

    public function removeTerm(CollectionId $collectionId, TermId $termId): bool
    {
        return DB::transaction(function () use ($collectionId, $termId): bool {
            $affected = DB::table('collection_items')
                ->where('collection_id', $collectionId->value)
                ->where('term_id', $termId->value)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);

            if ($affected === 0) {
                return false;
            }

            $this->refreshCount($collectionId);

            return true;
        });
    }

    public function purge(CollectionId $collectionId): bool
    {
        return DB::transaction(function () use ($collectionId): bool {
            $now = now();

            $affected = DB::table('collections')
                ->where('id', $collectionId->value)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            if ($affected === 0) {
                return false;
            }

            // Stamped, not deleted: the stamp IS the subscriber's tombstone.
            DB::table('user_collections')
                ->where('collection_id', $collectionId->value)
                ->whereNull('unsubscribed_at')
                ->update(['unsubscribed_at' => $now]);

            DB::table('collection_items')
                ->where('collection_id', $collectionId->value)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            return true;
        });
    }

    private function nextPosition(CollectionId $collectionId): int
    {
        $max = DB::table('collection_items')
            ->where('collection_id', $collectionId->value)
            ->max('position');

        return is_numeric($max) ? ((int) $max) + 1 : 0;
    }

    private function refreshCount(CollectionId $collectionId): void
    {
        $live = DB::table('collection_items')
            ->where('collection_id', $collectionId->value)
            ->whereNull('deleted_at')
            ->count();

        DB::table('collections')->where('id', $collectionId->value)->update([
            'items_count' => $live,
            'updated_at' => now(),
        ]);
    }
}
