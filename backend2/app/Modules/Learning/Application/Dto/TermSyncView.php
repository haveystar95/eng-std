<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Vocabulary\Application\Dto\TermContentView;
use DateTimeImmutable;

/** A changed term for delta sync: its timestamp plus hydrated content (from Vocabulary). */
final readonly class TermSyncView
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $updatedAt,
        public ?TermContentView $content,
    ) {}
}
