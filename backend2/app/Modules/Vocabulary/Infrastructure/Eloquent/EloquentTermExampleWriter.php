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
            DB::table('term_examples')->where('term_id', $termId->value)->delete();
            DB::table('term_examples')->insert([
                'id' => Ulid::generate(),
                'term_id' => $termId->value,
                'sentence' => $sentence,
                'sentence_translation' => $sentenceTranslation,
                'source' => 'user',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
