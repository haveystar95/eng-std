<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * A phrase line of the window: the phrase (its frame said with the dialogue's filler), how it reads, its
 * translation, its state and its voice — the learner's (DAY-UI-3) — and the frame itself (GEN-2a); where it is FROM
 * (наряд FIX-3 §9): `source` and the scene it belongs to.
 */
final readonly class WindowPhraseView
{
    public function __construct(
        public string $ref,
        public string $text,
        public string $translation,
        public string $state,
        public ?string $pronunciation = null,
        public ?string $audioId = null,
        public ?WindowFrameView $frame = null,
        public string $source = WindowSourceView::OWN,
        public ?WindowSourceView $scene = null,
    ) {}
}
