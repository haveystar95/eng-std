<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;

/**
 * Batch-hydrates renderable term content for other modules (Collections detail, Study
 * session cards) so nobody joins the terms tables from outside Vocabulary.
 */
interface TermContentReader
{
    /**
     * @param  list<TermId>  $termIds
     * @return array<string, TermContentView>  keyed by term id
     */
    public function byIds(array $termIds): array;
}
