<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTermReviewWriter implements TermReviewWriter
{
    public function __construct(private readonly LexicalNormalizer $normalizer) {}

    public function findTermIdByText(string $text): ?string
    {
        $id = DB::table('terms')->where('text', $text)->orderBy('id')->value('id');

        return $id !== null ? (string) $id : null;
    }

    public function removeDistractor(string $termId, string $sentence, bool $contains, string $source): int
    {
        $exampleId = $this->pinnedExampleId($termId);
        if ($exampleId === null) {
            return 0;
        }

        $query = DB::table('example_distractors')->where('example_id', $exampleId);
        $contains
            ? $query->where('sentence', 'like', '%' . $sentence . '%')
            : $query->where('sentence', $sentence);

        // Read the sentences before deleting them: `$contains` mode may match several rows, and each
        // is suppressed under its OWN text, not the fragment the review quoted.
        /** @var list<string> $removed */
        $removed = array_values(array_map('strval', $query->pluck('sentence')->all()));
        if ($removed === []) {
            return 0;
        }

        DB::transaction(function () use ($exampleId, $removed, $termId, $source): void {
            DB::table('example_distractors')->where('example_id', $exampleId)->whereIn('sentence', $removed)->delete();
            $this->suppress($termId, $removed, $source);
        });
        $this->touchTerm($termId);

        return count($removed);
    }

    public function suppressDistractor(string $termId, string $sentence, string $source): int
    {
        return $this->suppress($termId, [$sentence], $source);
    }

    /**
     * Record that this term's станок must never propose these sentences again — `insertOrIgnore`
     * against `(term_id, sentence)`, so re-removing an already-suppressed sentence (a re-applied review,
     * a second audit pass, a re-run backfill) is a no-op rather than an error. Returns how many were
     * newly written.
     *
     * @param  list<string>  $sentences
     */
    private function suppress(string $termId, array $sentences, string $source): int
    {
        $now = now();

        return DB::table('enrichment_suppressions')->insertOrIgnore(array_map(
            fn (string $sentence): array => [
                'id' => Ulid::generate(),
                'term_id' => $termId,
                'sentence' => $this->normalizer->canonicalize($sentence),
                'source' => $source,
                'created_at' => $now,
            ],
            $sentences,
        ));
    }

    public function fixDistractor(string $termId, string $sentence, string $errorSpan, string $correction): int
    {
        $exampleId = $this->pinnedExampleId($termId);
        if ($exampleId === null) {
            return 0;
        }

        $updated = DB::table('example_distractors')
            ->where('example_id', $exampleId)
            ->where('sentence', $sentence)
            ->update(['error_span' => $errorSpan, 'correction' => $correction, 'updated_at' => now()]);
        if ($updated > 0) {
            $this->touchTerm($termId);
        }

        return $updated;
    }

    public function setVariantNote(string $termId, string $text, ?string $note): int
    {
        $updated = DB::table('term_accepted_variants')
            ->where('term_id', $termId)
            ->where('text', $text)
            ->update(['note' => $note, 'updated_at' => now()]);
        if ($updated > 0) {
            $this->touchTerm($termId);
        }

        return $updated;
    }

    public function removeVariant(string $termId, string $text): int
    {
        $deleted = DB::table('term_accepted_variants')->where('term_id', $termId)->where('text', $text)->delete();
        if ($deleted > 0) {
            $this->touchTerm($termId);
        }

        return $deleted;
    }

    public function setPrimaryTranslation(string $termId, string $text): int
    {
        $updated = DB::table('term_translations')
            ->where('term_id', $termId)
            ->orderByDesc('is_primary')
            ->limit(1)
            ->update(['text' => $text, 'updated_at' => now()]);
        if ($updated > 0) {
            $this->touchTerm($termId);
        }

        return $updated;
    }

    public function setPinnedExampleTranslation(string $termId, string $text, string $lang): int
    {
        $exampleId = $this->pinnedExampleId($termId);
        if ($exampleId === null) {
            return 0;
        }

        $updated = DB::table('example_translations')
            ->where('term_example_id', $exampleId)
            ->where('lang', $lang)
            ->update(['text' => $text, 'updated_at' => now()]);

        // No row in this language yet: the reviewer is not correcting a gloss, they are supplying
        // the first one for their language. Written rather than reported as "nothing to fix" — the
        // count this method returns means «did the review land», and it did.
        if ($updated === 0) {
            DB::table('example_translations')->insert([
                'id' => Ulid::generate(),
                'term_example_id' => $exampleId,
                'lang' => $lang,
                'text' => $text,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $updated = 1;
        }

        $this->touchTerm($termId);

        return $updated;
    }

    /** The PINNED example — `orderBy('id')`, the same rule every other reader uses. */
    private function pinnedExampleId(string $termId): ?string
    {
        $id = DB::table('term_examples')->where('term_id', $termId)->orderBy('id')->value('id');

        return $id !== null ? (string) $id : null;
    }

    /**
     * A review changes what the device must mirror, and the delta feed decides by `terms.updated_at`.
     * Without this touch a removed variant would stay accepted on every already-synced phone — and a
     * variant we deleted BECAUSE it was wrong would keep grading wrong answers as right.
     */
    private function touchTerm(string $termId): void
    {
        DB::table('terms')->where('id', $termId)->update(['updated_at' => now()]);
    }
}
