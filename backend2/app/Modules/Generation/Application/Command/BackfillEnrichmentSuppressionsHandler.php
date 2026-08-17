<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\BackfillSuppressionsOutcome;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;

final readonly class BackfillEnrichmentSuppressionsHandler
{
    public function __construct(private TermReviewWriter $review) {}

    public function __invoke(BackfillEnrichmentSuppressions $command): BackfillSuppressionsOutcome
    {
        $inserted = 0;
        $alreadySuppressed = 0;
        $unmatched = [];

        foreach ($command->rows as $row) {
            $termId = $this->review->findTermIdByText($row['term']);
            if ($termId === null) {
                $unmatched[] = "«{$row['term']}»: термина нет в базе";

                continue;
            }

            $this->review->suppressDistractor($termId, $row['sentence'], $row['source']) > 0
                ? $inserted++
                : $alreadySuppressed++;
        }

        return new BackfillSuppressionsOutcome($inserted, $alreadySuppressed, $unmatched);
    }
}
