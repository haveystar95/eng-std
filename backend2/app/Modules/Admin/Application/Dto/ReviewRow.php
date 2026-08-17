<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One entry in a user's review feed. Ordering truth is client_seq; answered_at is reference time. */
final readonly class ReviewRow
{
    public function __construct(
        public string $id,
        public string $termId,
        public ?string $termText,
        public ?string $exerciseMode,
        public string $grade,
        public ?bool $isCorrect,
        public bool $isPractice,
        public int $clientSeq,
        public ?string $answeredAt,
    ) {}
}
