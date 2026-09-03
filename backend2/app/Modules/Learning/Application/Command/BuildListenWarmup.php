<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «Хочешь, настрою точнее?» — the entry asking for three lines of the situation (кадр V4·03).
 *
 * A command with no plan id, because there is no plan: the step stands between the level and the
 * date. The support language is not here either — it is the account's own, read where every other
 * plan reads it.
 */
final readonly class BuildListenWarmup
{
    public function __construct(
        public UserId $actorId,
        public string $goalText,
        /**
         * EMPTY on the goal step, where the language has not been chosen yet.
         *
         * The same command serves both moments of the entry: typed-goal («допиши за меня», no
         * language) and after the level («послушай три реплики»). One command, because it is one
         * question about one goal and one paid call — see `plan_listen.v1.1`.
         */
        public string $targetLang,
        public string $level,
    ) {}
}
