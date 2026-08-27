<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A pair that ROSE A RUNG today, and the rung it now stands on — «награда дня», before content.
 *
 * Two numbers rather than one because the screen says two things about the same event: how many
 * words moved («+5 слов продвинулись») and where ONE of them got to («reluctant дошло до
 * „написание"»). The rung travels as a NUMBER; naming it «написание» is the client's job, in the
 * client's language — the server has no business spelling a rung in Russian.
 */
final readonly class TermPromotionFact
{
    /**
     * @param  int  $fromStep  the rung the day started on — the rung the first card of the day was dealt at
     * @param  int  $toStep    the rung the pair stands on now
     */
    public function __construct(
        public string $termId,
        public int $fromStep,
        public int $toStep,
    ) {}
}
