<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Everything P-Listen is told — and there is no plan among it.
 *
 * The listening step of the entry stands between the level and the date (кадр V4·03), and the plan
 * is not created until the button after the date. So this brief carries the four facts the learner
 * has already given and the id of the person asking, which is what the ledger row needs; `plan_id`
 * on that row is NULL, honestly, because there is nothing yet for it to name.
 */
final readonly class ListenWarmupBrief
{
    public function __construct(
        public string $userId,
        public string $goalText,
        public string $supportLang,
        public string $targetLang,
        public string $level,
    ) {}
}
