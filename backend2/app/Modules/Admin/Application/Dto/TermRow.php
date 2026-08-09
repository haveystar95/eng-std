<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One row of the admin terms list. */
final readonly class TermRow
{
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $type,
        public ?string $translation,
        public ?string $createdAt,
    ) {}
}
