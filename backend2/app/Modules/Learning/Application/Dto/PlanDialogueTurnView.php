<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/** One turn of a scene's conversation on the wire — see {@see PlanDialogueView}. */
final readonly class PlanDialogueTurnView
{
    public function __construct(
        /** `role` — the other person speaks; `you` — the learner's move. */
        public string $turn,
        public string $termId,
        /** The line itself, on the language being learned. */
        public string $text,
        /** Its translation — what «Показать текст» never shows and the cheat sheet does. */
        public ?string $translation,
        /** `hear` | `say` | `ask` — which shelf the card stands on, and therefore its ladder. */
        public ?string $shelf,
    ) {}
}
