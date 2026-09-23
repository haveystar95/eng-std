<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * A line as the CLIENT is given it, next to its sound (наряд ADM-1, доработка): the text the answer shows (`GET
 * …/days/{n}/cards` — a card, «Вспомнить» among them — or `GET …/days/{n}`), the id of the file the answer tells the
 * phone to play, that file's own `<scene>:<ref>`, and the file's text. `fileRef` null — the id names no file of the
 * plan; `soundText` null — the file's text is not known (then nothing can be said about it). `fragment` — the text is
 * an option, a chip or a filler: its sound is the phrase said WITH it, so the sound must hold the text, not equal it.
 */
final readonly class CardSoundFact
{
    public function __construct(
        public int $day,
        public string $answer,
        public string $place,
        public string $kind,
        public string $path,
        public string $audioId,
        public ?string $fileRef,
        public string $cardText,
        public ?string $soundText,
        public bool $fragment = false,
    ) {}
}
