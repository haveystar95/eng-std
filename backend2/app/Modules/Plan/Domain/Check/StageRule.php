<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/**
 * ONE NAMED RULE OF A DAY'S STAGE (наряд GEN-4) — of the skeleton ({@see Skeleton\SkeletonRule}) or of the dialogue
 * ({@see Dialogue\DialogueRule}). Its code is the name of what it finds: the code of every finding, the name a repair is
 * told, the key of the check counters. A FATAL rule's finding asks its stage once more; a warning's finding sends its card
 * to a repair. Code only, no meaning: every rule reads what is written — counts, ids, strings, a language's pack.
 */
interface StageRule
{
    public function code(): string;

    public function fatal(): bool;
}
