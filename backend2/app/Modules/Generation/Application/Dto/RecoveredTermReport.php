<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

final readonly class RecoveredTermReport
{
    /**
     * @param  'planned'|'recovered'|'already_present'|'unrecoverable'  $status
     */
    public function __construct(
        public string $collectionTitle,
        public string $collectionId,
        public string $text,
        public string $status,
        public ?string $termId = null,
        public ?string $reason = null,
    ) {}
}
