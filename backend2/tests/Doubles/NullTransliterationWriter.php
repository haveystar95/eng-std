<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermTransliterationWriter;

/** The sibling of {@see NullTermEnrichmentWriter}, for the same reason. */
final class NullTransliterationWriter implements TermTransliterationWriter
{
    public function ensure(
        TermId $termId,
        string $lang,
        string $text,
        string $source = 'auto',
        ?string $generatorVersion = null,
    ): bool {
        return false;
    }
}
