<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** One line of a script: who says it (a key of {@see SpeechScript::$voices}) and what. */
final readonly class SpeechTurn
{
    public function __construct(
        public string $speaker,
        public string $text,
    ) {}
}
