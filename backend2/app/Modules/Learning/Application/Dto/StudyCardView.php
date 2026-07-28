<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/** A due card ready to render: Learning's scheduling facts + Vocabulary's term content. */
final readonly class StudyCardView
{
    public function __construct(
        public string $termId,
        public string $state,
        public int $intervalDays,
        public ?DateTimeImmutable $dueAt,
        public ?string $text,
        public ?string $type,
        public ?string $transcription,
        public ?string $translation,
        public ?string $example,
        public ?string $exampleTranslation,
    ) {}
}
