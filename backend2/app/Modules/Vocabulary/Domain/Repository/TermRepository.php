<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Repository;

use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Shared\Domain\ValueObject\TermId;

interface TermRepository
{
    /** The dedup lookup: one term per (lang, normalized_text, pos). */
    public function findByDedup(LanguageCode $lang, string $normalizedText, ?PartOfSpeech $pos): ?Term;

    public function findById(TermId $id): ?Term;

    public function save(Term $term): void;
}
