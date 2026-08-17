<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Dto\ExampleRegenContext;
use App\Modules\Vocabulary\Application\Query\ExampleRegenContextReader;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Support\Facades\DB;

final class EloquentExampleRegenContextReader implements ExampleRegenContextReader
{
    public function find(TermId $termId): ?ExampleRegenContext
    {
        $term = DB::table('terms')->where('id', $termId->value)->first(['text', 'lang']);
        if ($term === null) {
            return null;
        }

        $currentExample = DB::table('term_examples')
            ->where('term_id', $termId->value)
            ->orderBy('id')
            ->value('sentence');

        // The language the example's translation should be in: the term's primary translation lang.
        $translationLang = DB::table('term_translations')
            ->where('term_id', $termId->value)
            ->orderByDesc('is_primary')
            ->value('lang');

        return new ExampleRegenContext(
            text: (string) $term->text,
            lang: (string) $term->lang,
            currentExample: $currentExample !== null ? (string) $currentExample : null,
            translationLang: $translationLang !== null ? (string) $translationLang : null,
        );
    }
}
