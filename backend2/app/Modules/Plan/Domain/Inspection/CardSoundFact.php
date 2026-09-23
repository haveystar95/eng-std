<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * A line on a dealt card next to the sound it plays: the text the card shows and the text the lesson says at the ref its
 * sound stub names (`audio.ref`), in the scene the card names. `lessonText` null — the ref names no line of that scene.
 */
final readonly class CardSoundFact
{
    public function __construct(
        public int $day,
        public string $cardId,
        public string $kind,
        public string $path,
        public string $audioRef,
        public string $cardText,
        public ?string $lessonText,
    ) {}
}
