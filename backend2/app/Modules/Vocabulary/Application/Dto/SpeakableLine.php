<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/** Реплика, которую надо озвучить: что сказать и на каком языке. */
final readonly class SpeakableLine
{
    public function __construct(
        public string $termId,
        public string $text,
        public string $lang,
    ) {}
}
