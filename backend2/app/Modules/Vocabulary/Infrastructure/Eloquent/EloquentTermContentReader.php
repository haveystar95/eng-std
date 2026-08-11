<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use Illuminate\Support\Facades\DB;

final class EloquentTermContentReader implements TermContentReader
{
    public function byIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        $translations = [];
        foreach (DB::table('term_translations')->whereIn('term_id', $ids)->orderByDesc('is_primary')->get() as $row) {
            $translations[(string) $row->term_id] ??= (string) $row->text;
        }

        // A term may hold several examples (ImportTerm appends one per generation pass), but a card
        // shows exactly one — so which one must be PINNED, not whichever the heap hands back. Without
        // an explicit order an unordered scan can return a different row after any UPDATE to the
        // table, i.e. the same term would show a different example between two requests, and the
        // client (which mirrors one example per term via /sync) would disagree with the card the
        // server built. `id` is a ULID, so ordering by it pins the term's FIRST example and keeps
        // the same one for good. Same order as EloquentExampleRegenContextReader, so "New example"
        // replaces the example the user is actually looking at.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get() as $row) {
            $examples[(string) $row->term_id] ??= $row;
        }

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get() as $term) {
            $id = (string) $term->id;
            $example = $examples[$id] ?? null;

            $out[$id] = new TermContentView(
                id: $id,
                lang: (string) $term->lang,
                text: (string) $term->text,
                type: (string) $term->type,
                transcription: $term->ipa !== null ? (string) $term->ipa : null,
                translation: $translations[$id] ?? null,
                example: $example !== null && $example->sentence !== null ? (string) $example->sentence : null,
                exampleTranslation: $example !== null && $example->sentence_translation !== null ? (string) $example->sentence_translation : null,
                imageUrl: $term->image_url !== null ? (string) $term->image_url : null,
                imageAuthor: $term->image_author !== null ? (string) $term->image_author : null,
                imageAuthorUrl: $term->image_author_url !== null ? (string) $term->image_author_url : null,
            );
        }

        return $out;
    }
}
