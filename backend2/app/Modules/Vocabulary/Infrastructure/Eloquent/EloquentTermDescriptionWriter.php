<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;
use Illuminate\Support\Facades\DB;

final class EloquentTermDescriptionWriter implements TermDescriptionWriter
{
    public function ensure(
        TermId $termId,
        string $lang,
        string $text,
        string $source = 'ai',
        ?string $promptVersion = null,
        ?string $generationModel = null,
    ): bool {
        $clean = trim($text);
        if ($clean === '') {
            return false;
        }

        // insertOrIgnore rather than read-then-write: two learners can look the same new word up in
        // the same second, and the unique index is the only thing that makes «one description» true
        // under a race. A conflict is the normal outcome here, not an error.
        return DB::table('term_descriptions')->insertOrIgnore([
            'id' => (string) Ulid::generate(),
            'term_id' => $termId->value,
            'lang' => $lang,
            'text' => $clean,
            'source' => $source,
            'prompt_version' => $promptVersion,
            'generation_model' => $generationModel,
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0;
    }
}
