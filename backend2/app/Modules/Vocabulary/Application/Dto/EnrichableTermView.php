<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** A user term that still needs enrichment: its text, type and language (what's being learned). */
final readonly class EnrichableTermView
{
    public function __construct(
        public string $id,
        public string $text,
        public string $type,
        public string $lang,
    ) {}
}
