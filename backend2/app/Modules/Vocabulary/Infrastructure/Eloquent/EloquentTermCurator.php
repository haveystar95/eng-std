<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Dto\TermImpact;
use App\Modules\Vocabulary\Application\Port\TermCurator;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use Illuminate\Support\Facades\DB;

/**
 * Curation writes over the Vocabulary tables.
 *
 * Every write bumps `terms.updated_at`, including the ones that only touch a child table
 * (translations, examples): the delta sync detects a changed term by that column alone, so an edit
 * that forgets to bump it is an edit the phone never hears about.
 */
final class EloquentTermCurator implements TermCurator
{
    public function __construct(private readonly TermNormalizer $normalizer) {}

    public function impact(TermId $termId): ?TermImpact
    {
        $term = DB::table('terms')->where('id', $termId->value)->whereNull('deleted_at')->first(['id', 'text']);
        if ($term === null) {
            return null;
        }

        return new TermImpact(
            termId: (string) $term->id,
            text: (string) $term->text,
            collectionsCount: DB::table('collection_items as ci')
                ->join('collections as c', 'c.id', '=', 'ci.collection_id')
                ->where('ci.term_id', $termId->value)
                ->whereNull('ci.deleted_at')
                ->whereNull('c.deleted_at')
                ->count(),
            usersWithProgress: DB::table('user_term_progress')->where('term_id', $termId->value)->count(),
            reviewsCount: DB::table('reviews')->where('term_id', $termId->value)->count(),
        );
    }

    public function updateContent(TermId $termId, ?string $text, ?string $translation, ?string $ipa): bool
    {
        return DB::transaction(function () use ($termId, $text, $translation, $ipa): bool {
            $term = DB::table('terms')->where('id', $termId->value)->whereNull('deleted_at')->first();
            if ($term === null) {
                return false;
            }

            $changes = ['updated_at' => now()];
            if ($text !== null && $text !== (string) $term->text) {
                $changes['text'] = $text;
                // The normalized form is what dedup keys on, so it has to move with the text.
                $changes['normalized_text'] = $this->normalizer->normalize(
                    $text,
                    TermType::from((string) $term->type),
                );
            }
            if ($ipa !== null) {
                $changes['ipa'] = $ipa !== '' ? $ipa : null;
            }
            DB::table('terms')->where('id', $termId->value)->update($changes);

            if ($translation !== null) {
                $this->setPrimaryTranslation($termId, (string) $term->lang, $translation);
            }

            return true;
        });
    }

    public function updateExample(TermId $termId, string $exampleId, string $sentence, ?string $translation): bool
    {
        return DB::transaction(function () use ($termId, $exampleId, $sentence, $translation): bool {
            $example = DB::table('term_examples')
                ->where('id', $exampleId)
                ->where('term_id', $termId->value)
                ->first(['id', 'sentence']);
            if ($example === null) {
                return false;
            }

            DB::table('term_examples')->where('id', $exampleId)->update([
                'sentence' => $sentence,
                'sentence_translation' => $translation,
                'source' => 'user',
                'updated_at' => now(),
            ]);

            // The sentence changed, so every distractor built from it is now a distractor of a
            // sentence nobody will see. Drop them rather than leave the trainer serving nonsense.
            if ((string) $example->sentence !== $sentence) {
                DB::table('example_distractors')->where('example_id', $exampleId)->delete();
                // Unmark the term: the станок picks up whatever has no version mark, so this is
                // what puts it back in the queue.
                DB::table('term_enrichment_versions')->where('term_id', $termId->value)->delete();
            }

            DB::table('terms')->where('id', $termId->value)->update(['updated_at' => now()]);

            return true;
        });
    }

    public function retire(TermId $termId): bool
    {
        return DB::transaction(function () use ($termId): bool {
            $exists = DB::table('terms')->where('id', $termId->value)->whereNull('deleted_at')->exists();
            if (! $exists) {
                return false;
            }

            $now = now();

            // Out of every deck first: soft-deleted items are the tombstones the clients read.
            DB::table('collection_items')
                ->where('term_id', $termId->value)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $now, 'updated_at' => $now]);

            $this->refreshItemCounts($termId);

            // Progress is a live state row, not history — it goes. `reviews` is history and stays:
            // the append-only log is an invariant, and a retired term does not un-happen the
            // answers somebody gave.
            DB::table('user_term_progress')->where('term_id', $termId->value)->delete();

            DB::table('terms')->where('id', $termId->value)->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

            return true;
        });
    }

    private function setPrimaryTranslation(TermId $termId, string $termLang, string $text): void
    {
        $existing = DB::table('term_translations')
            ->where('term_id', $termId->value)
            ->orderByDesc('is_primary')
            ->first(['id', 'lang']);

        if ($existing !== null) {
            DB::table('term_translations')->where('id', $existing->id)->update([
                'text' => $text,
                'is_primary' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        // No translation yet (possible for an imported term): create one in the paired language.
        DB::table('term_translations')->insert([
            'id' => Ulid::generate(),
            'term_id' => $termId->value,
            'lang' => $termLang === 'en' ? 'ru' : 'en',
            'text' => $text,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Keep the denormalised counter honest for every collection the term just left. */
    private function refreshItemCounts(TermId $termId): void
    {
        $collectionIds = DB::table('collection_items')
            ->where('term_id', $termId->value)
            ->distinct()
            ->pluck('collection_id');

        foreach ($collectionIds as $collectionId) {
            $live = DB::table('collection_items')
                ->where('collection_id', $collectionId)
                ->whereNull('deleted_at')
                ->count();

            DB::table('collections')->where('id', $collectionId)->update([
                'items_count' => $live,
                'updated_at' => now(),
            ]);
        }
    }
}
