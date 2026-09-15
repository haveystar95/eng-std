<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * The frame behind a phrase of the window (`lesson_day.v4.4`): the pattern with its `___` in both
 * languages and read aloud, its kind, and its slot — the hint and the fillers, those the dialogue says
 * marked, each with the voice of the frame said with it (TTS-2). Additive (GEN-2a): the client does not
 * read it yet.
 */
final readonly class WindowFrameView
{
    /**
     * @param  array{hint: string, fillers: list<array{target: string, native: string, pronunciation: string, in_dialogue: bool, audio_id: string|null}>}|null  $slot
     */
    public function __construct(
        public string $target,
        public string $native,
        public string $pronunciation,
        public string $kind,
        public ?array $slot,
    ) {}
}
