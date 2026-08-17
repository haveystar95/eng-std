<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;

/** Replaces a term's stored examples with a single new one. Vocabulary owns term_examples. */
interface TermExampleWriter
{
    public function replace(TermId $termId, string $sentence, ?string $sentenceTranslation): void;
}
