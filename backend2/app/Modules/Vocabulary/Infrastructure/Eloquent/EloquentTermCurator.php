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

    public function updateContent(TermId $termId, ?string $text, ?string $translation, ?string $ipa, string $translationLang): bool
    {
        return DB::transaction(function () use ($termId, $text, $translation, $ipa, $translationLang): bool {
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
                $this->setPrimaryTranslation($termId, (string) $term->lang, $translationLang, $translation);
            }

            return true;
        });
    }

    public function updateExample(TermId $termId, string $exampleId, string $sentence, ?string $translation, string $translationLang): bool
    {
        return DB::transaction(function () use ($termId, $exampleId, $sentence, $translation, $translationLang): bool {
            $example = DB::table('term_examples')
                ->where('id', $exampleId)
                ->where('term_id', $termId->value)
                ->first(['id', 'sentence']);
            if ($example === null) {
                return false;
            }

            DB::table('term_examples')->where('id', $exampleId)->update([
                'sentence' => $sentence,
                'source' => 'user',
                'updated_at' => now(),
            ]);

            $sentenceChanged = (string) $example->sentence !== $sentence;

            // A gloss the operator did not touch, in a language they were not editing, describes the
            // sentence they just rewrote — so it goes with the distractors, and only then. An edit
            // that left the sentence alone is an edit of ONE language's wording and nothing else.
            if ($sentenceChanged) {
                DB::table('example_translations')
                    ->where('term_example_id', $exampleId)
                    ->where('lang', '!=', $translationLang)
                    ->delete();
            }
            $this->writeExampleTranslation($exampleId, $translation, $translationLang);

            // The sentence changed, so every distractor built from it is now a distractor of a
            // sentence nobody will see. Drop them rather than leave the trainer serving nonsense.
            if ($sentenceChanged) {
                DB::table('example_distractors')->where('example_id', $exampleId)->delete();
                // Unmark the term: the станок picks up whatever has no version mark, so this is
                // what puts it back in the queue.
                DB::table('term_enrichment_versions')->where('term_id', $termId->value)->delete();
            }

            DB::table('terms')->where('id', $termId->value)->update(['updated_at' => now()]);

            return true;
        });
    }

    public function dropTranslation(TermId $termId, string $translationId): bool
    {
        return DB::transaction(function () use ($termId, $translationId): bool {
            $rows = DB::table('term_translations')->where('term_id', $termId->value)->count();
            if ($rows <= 1) {
                return false;
            }

            $deleted = DB::table('term_translations')
                ->where('id', $translationId)
                ->where('term_id', $termId->value)
                ->delete();

            if ($deleted === 0) {
                return false;
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

    /**
     * Rewrite the term's translation IN $lang — the same row a reader asking in $lang would show,
     * picked by the same total order ({@see TranslationPick::ordered()}) so an edit can never land on
     * a different row than the one the operator was looking at.
     *
     * `lang` moves with the text, and that is the half this used to omit. The retrospective repair
     * (RepairContentLanguageHandler) writes through here: it asked the model for Russian, wrote
     * Russian into the row, and left the row labelled `uk` or `de` — 118 live rows ended up holding
     * Russian text under a foreign label, which is precisely what made a language-aware reader see
     * "no Russian translation" for terms that had one all along.
     */
    private function setPrimaryTranslation(TermId $termId, string $termLang, string $lang, string $text): void
    {
        $existing = TranslationPick::ordered(
            DB::table('term_translations')->where('term_id', $termId->value),
            $lang,
        )->first(['id', 'lang']);

        if ($existing !== null) {
            DB::table('term_translations')->where('id', $existing->id)->update([
                'text' => $text,
                'lang' => $lang,
                'is_primary' => true,
                'updated_at' => now(),
            ]);

            return;
        }

        // No translation yet (possible for an imported term): create one. $lang is what the caller
        // asked for; the paired-language guess only stands in when nobody said.
        DB::table('term_translations')->insert([
            'id' => Ulid::generate(),
            'term_id' => $termId->value,
            'lang' => $lang !== '' ? $lang : ($termLang === 'en' ? 'ru' : 'en'),
            'text' => $text,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * The example's gloss in ONE language: rewritten if it is there, created if it is not, removed
     * when the operator cleared the field.
     *
     * Removal is a delete rather than an empty string, because `example_translations` answers "is
     * there a gloss in this language" by the presence of a row — an empty row would read as "yes,
     * and it says nothing", which is the answer the reader cannot distinguish from a real one.
     */
    private function writeExampleTranslation(string $exampleId, ?string $text, string $lang): void
    {
        $rows = DB::table('example_translations')->where('term_example_id', $exampleId)->where('lang', $lang);

        if ($text === null || trim($text) === '') {
            $rows->delete();

            return;
        }

        if ($rows->update(['text' => $text, 'updated_at' => now()]) === 0) {
            DB::table('example_translations')->insert([
                'id' => Ulid::generate(),
                'term_example_id' => $exampleId,
                'lang' => $lang,
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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
