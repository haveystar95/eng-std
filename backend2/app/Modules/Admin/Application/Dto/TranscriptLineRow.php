<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

final readonly class TranscriptLineRow
{
    public function __construct(
        public string $role,   // user | assistant
        public string $text,
        public int $ts,        // client line timestamp (ms)
    ) {}
}
