<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * Один ход цепочки разговора, сплющенный до двух вещей, которые нужны сборщику ситуации.
 *
 * Своё DTO, а не `PlanDialogueTurnView`, по той же причине, что и {@see SituationalCandidate}:
 * {@see \App\Modules\Learning\Domain\Service\SituationalPrompt} — чистый Domain и не импортирует ни
 * Application, ни соседние модули, а сплющивание — одна строка в единственном вызывающем, у
 * которого цепочка уже есть.
 */
final readonly class SituationalTurn
{
    public function __construct(
        /** `role` — говорит собеседник; `you` — ход человека. */
        public string $side,
        public string $termId,
    ) {}

    public const SIDE_ROLE = 'role';
    public const SIDE_YOU = 'you';
}
