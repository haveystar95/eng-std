<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «Продолжить „Ветклинику“ — 4 из 16 слов · брошено 5 дней назад», and the same collection under
 * its evening name, «можно добить N слов сверх плана».
 *
 * One candidate, one object: the two lines are two renderings of a single fact — a collection the
 * learner started and left. «Started» is required (`done > 0`): a set added an hour ago and never
 * opened has not been abandoned, it is simply new, and offering to «continue» it would be wrong.
 */
final readonly class HomeContinueView
{
    public function __construct(
        public string $collectionId,
        public string $title,
        /** Terms of the collection that have a verdict or a progress row. */
        public int $done,
        public int $total,
        /** total - done: the swipes still waiting in it. Always >= 1, or this candidate would not exist. */
        public int $remaining,
        /** Whole days since the last swipe or answer on any of its terms; null if that moment is unknown. */
        public ?int $abandonedDays,
    ) {}
}
