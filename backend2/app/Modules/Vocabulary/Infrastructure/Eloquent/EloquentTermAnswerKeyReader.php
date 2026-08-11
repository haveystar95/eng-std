<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermAnswerKeyView;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use Illuminate\Support\Facades\DB;

final class EloquentTermAnswerKeyReader implements TermAnswerKeyReader
{
    public function byIds(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // The term's PINNED example — ordered by id, the same one EloquentTermContentReader put on
        // the card. A sentence exercise is graded against the sentence the learner actually saw, so
        // these two reads must agree; ordering is what makes them agree.
        $examples = [];
        foreach (DB::table('term_examples')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'sentence']) as $row) {
            $examples[(string) $row->term_id] ??= (string) $row->sentence;
        }

        // The alternative forms this reader's shape was always waiting for: variants the enrichment
        // станок validated (`this`/`that`, `may`/`can`, colour/color). Still only target-language
        // text the term itself owns — a translation is never an accepted answer.
        $variants = [];
        foreach (DB::table('term_accepted_variants')->whereIn('term_id', $ids)->orderBy('id')->get(['term_id', 'text']) as $row) {
            $variants[(string) $row->term_id][] = (string) $row->text;
        }

        $out = [];
        foreach (DB::table('terms')->whereIn('id', $ids)->get(['id', 'text', 'type']) as $row) {
            $id = (string) $row->id;
            $out[$id] = new TermAnswerKeyView(
                termId: $id,
                accepted: [(string) $row->text, ...($variants[$id] ?? [])],
                isPhrase: TermType::from((string) $row->type)->isPhraseLike(),
                example: $examples[$id] ?? null,
            );
        }

        return $out;
    }
}
