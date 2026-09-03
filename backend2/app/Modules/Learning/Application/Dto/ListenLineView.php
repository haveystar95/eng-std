<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * One line of the listening warm-up, as the player screen reads it (кадр V4·03б).
 *
 * `place` is the надзаголовок over the play button — «Реплика 2 · на стойке». It is prose from the
 * model in the support language and is rendered, never matched: a scene the model invents needs a
 * name the model invents.
 */
final readonly class ListenLineView
{
    public function __construct(
        public string $text,
        public string $translation,
        public string $place,
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'translation' => $this->translation,
            'place' => $this->place,
        ];
    }
}
