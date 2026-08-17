<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A practice dialog with its transcript and recorded (estimated) cost. */
final readonly class DialogDetail
{
    /** @param list<TranscriptLineRow> $transcript */
    public function __construct(
        public string $id,
        public string $userId,
        public string $collectionId,
        public string $status,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?float $costUsd,
        public ?string $summary,
        public ?string $createdAt,
        public ?string $finishedAt,
        public array $transcript,
    ) {}
}
