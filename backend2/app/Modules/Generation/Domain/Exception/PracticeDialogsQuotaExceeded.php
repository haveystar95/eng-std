<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DateTimeImmutable;
use DomainException;

/**
 * The user has started their allowed number of practice dialogs today. `resets_at` is an absolute
 * UTC instant (the next day boundary the quota counts against); the client renders it locally.
 */
final class PracticeDialogsQuotaExceeded extends DomainException implements ProblemDetails
{
    private function __construct(
        public readonly int $limit,
        public readonly DateTimeImmutable $resetsAt,
    ) {
        parent::__construct("Daily practice-dialog limit of {$limit} reached.");
    }

    public static function perDay(int $limit, DateTimeImmutable $resetsAt): self
    {
        return new self($limit, $resetsAt);
    }

    public function problemStatus(): int
    {
        return 429;
    }

    public function problemCode(): string
    {
        return 'practice_dialogs_quota_exceeded';
    }

    public function problemTitle(): string
    {
        return 'Daily practice limit reached';
    }

    public function problemMeta(): array
    {
        return [
            'limit' => $this->limit,
            'resets_at' => $this->resetsAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
