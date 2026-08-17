<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One entry of the learner selector on the ladder screen.
 *
 * `lastActivityAt` is the later of the last answered review and the last intro shown — the two
 * things a phone actually does during a session. Answers alone would leave a learner who has only
 * been introduced to new words looking idle.
 */
final readonly class AdminLadderLearner
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $lastActivityAt,
        public int $pairsCount,
    ) {}
}
