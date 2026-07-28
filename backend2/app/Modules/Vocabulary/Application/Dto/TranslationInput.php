<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/** A translation as supplied by another module — primitives + Shared VOs only. */
final readonly class TranslationInput
{
    public function __construct(
        public LanguageCode $lang,
        public string $text,
        public bool $isPrimary = false,
    ) {}
}
