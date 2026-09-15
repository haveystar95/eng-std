<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use InvalidArgumentException;

/**
 * ONE LINE SAID ON ITS OWN CALL (TTS-2): a phrase, a phrase with one of its fillers, a word. The vendor bills by the
 * character, so there is nothing to gain by packing lines together — and a line of its own comes back as its own
 * file, with nothing to cut.
 */
final readonly class SpeechLine
{
    public function __construct(
        public string $text,
        public LineVoice $voice,
    ) {
        if (trim($text) === '') {
            throw new InvalidArgumentException('a line to say is empty');
        }
    }
}
