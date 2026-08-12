<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTermReviewWriter implements TermReviewWriter
{
    public function findTermIdByText(string $text): ?string
    {
        $id = DB::table('terms')->where('text', $text)->orderBy('id')->value('id');

        return $id !== null ? (string) $id : null;
    }

    public function removeDistractor(string $termId, string $sentence, bool $contains = false): int
    {
        $exampleId = $this->pinnedExampleId($termId);
        if ($exampleId === null) {
            return 0;
        }

        $query = DB::table('example_distractors')->where('example_id', $exampleId);
        $contains
            ? $query->where('sentence', 'like', '%' . $sentence . '%')
            : $query->where('sentence', $sentence);

        $deleted = $query->delete();
        if ($deleted > 0) {
            $this->touchTerm($termId);
        }

        return $deleted;
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

    public function setPinnedExampleTranslation(string $termId, string $text): int
    {
        $exampleId = $this->pinnedExampleId($termId);
        if ($exampleId === null) {
            return 0;
        }

        $updated = DB::table('term_examples')
            ->where('id', $exampleId)
            ->update(['sentence_translation' => $text, 'updated_at' => now()]);
        if ($updated > 0) {
            $this->touchTerm($termId);
        }

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
