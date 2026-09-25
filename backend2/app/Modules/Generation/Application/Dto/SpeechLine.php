<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use InvalidArgumentException;

/**
 * ONE LINE SAID ON ITS OWN CALL (TTS-2): a phrase, a phrase with one of its fillers, a word. The vendor bills by the
 * character, so there is nothing to gain by packing lines together — and a line of its own comes back as its own
 * file, with nothing to cut.
 *
 * THE LINE'S LANGUAGE (наряд LANG-1, п. 9): `$languageCode` is the language the line is in — the plan's target, ISO
 * 639-1 lower-case (`de`, `pl`) — handed to the vendor so the multilingual model says a Polish line the Polish way instead
 * of guessing from the letters. It is a fact of the line, not of the voice: the same six voices speak every target, and
 * the file's address ({@see LineVoice::key()}) stays what it was (DECISIONS п. 248). Null — the language is not named
 * and the vendor guesses, as it did before LANG-1.
 */
final readonly class SpeechLine
{
    public ?string $languageCode;

    public function __construct(
        public string $text,
        public LineVoice $voice,
        ?string $languageCode = null,
    ) {
        if (trim($text) === '') {
            throw new InvalidArgumentException('a line to say is empty');
        }
        $code = strtolower(trim((string) $languageCode));
        if ($code !== '' && preg_match('/^[a-z]{2,3}$/', $code) !== 1) {
            throw new InvalidArgumentException("a line's language is not a language code: {$languageCode}");
        }
        $this->languageCode = $code === '' ? null : $code;
    }
}
