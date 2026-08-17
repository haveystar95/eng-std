<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

final readonly class TermTranslationRow
{
    public function __construct(
        public string $lang,
        public string $text,
        public bool $isPrimary,
    ) {}
}
