<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\RecordsGenerationRejections;
use App\Modules\Generation\Domain\ValueObject\RejectedItem;

final class RecordingRejectionJournal implements RecordsGenerationRejections
{
    /** @var list<array{requestId: string, rejections: list<RejectedItem>}> */
    public array $recorded = [];

    public function record(string $requestId, array $rejections): void
    {
        $this->recorded[] = ['requestId' => $requestId, 'rejections' => $rejections];
    }

    /** @return list<RejectedItem> */
    public function all(): array
    {
        $out = [];
        foreach ($this->recorded as $call) {
            foreach ($call['rejections'] as $rejection) {
                $out[] = $rejection;
            }
        }

        return $out;
    }
}
