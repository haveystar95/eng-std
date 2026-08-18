<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentTermExampleWriter implements TermExampleWriter
{
    public function replace(TermId $termId, string $sentence, ?string $sentenceTranslation): void
    {
        DB::transaction(function () use ($termId, $sentence, $sentenceTranslation): void {
            $now = now();

            DB::table('term_examples')->where('term_id', $termId->value)->delete();
            DB::table('term_examples')->insert([
                'id' => Ulid::generate(),
                'term_id' => $termId->value,
                'sentence' => $sentence,
                'sentence_translation' => $sentenceTranslation,
                'source' => 'user',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // The delta feed decides what to ship by `terms.updated_at`, and the example lives in
            // ANOTHER table — so without this touch a replaced example stayed on the server and the
            // phone kept showing the old sentence forever (QA-19). Which is the worse half of the
            // bug: both callers exist to FIX a bad example — «Новый пример» by hand and the echo
            // repair automatically — and the fix reached everything except the device that had the
            // complaint. Unconditional, unlike the enrichment writer's version of this line: a
            // replace always writes a different sentence, so there is no no-op run to guard against.
            DB::table('terms')->where('id', $termId->value)->update(['updated_at' => $now]);
        });
    }
}
