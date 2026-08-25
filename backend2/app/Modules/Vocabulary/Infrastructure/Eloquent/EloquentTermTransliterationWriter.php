<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermTransliterationWriter;
use App\Modules\Vocabulary\Domain\ValueObject\SynonymSource;
use App\Modules\Vocabulary\Domain\ValueObject\Transliteration;
use Illuminate\Support\Facades\DB;

final class EloquentTermTransliterationWriter implements TermTransliterationWriter
{
    public function ensure(
        TermId $termId,
        string $lang,
        string $text,
        string $source = 'auto',
        ?string $generatorVersion = null,
    ): bool {
        if (trim($text) === '') {
            return false;
        }

        // Through the value object, so the string is canonicalised by the same gate every other
        // content string passes and an unknown `source` fails here rather than at the CHECK.
        $hint = new Transliteration(new LanguageCode($lang), $text, SynonymSource::from($source));

        // insertOrIgnore against `term_transliterations_uidx`, like the description writer: two
        // collections can pull the same word in at the same moment, and the unique index is what
        // makes «one hint per pair» true under a race. A conflict is the normal outcome.
        $written = DB::table('term_transliterations')->insertOrIgnore([
            'id' => (string) Ulid::generate(),
            'term_id' => $termId->value,
            'lang' => $hint->lang->value,
            'text' => $hint->text,
            'source' => $hint->source->value,
            'generator_version' => $generatorVersion,
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0;

        // The delta feed decides what to ship by `terms.updated_at`, and this row lives in ANOTHER
        // table — without the touch the hint would sit on the server invisible to every already
        // synced device. Only when something was actually inserted.
        if ($written) {
            DB::table('terms')->where('id', $termId->value)->update(['updated_at' => now()]);
        }

        return $written;
    }
}
