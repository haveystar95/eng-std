<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\RepairedTranslation;
use App\Modules\Generation\Application\Dto\TranslationRepairBrief;

/**
 * Translates one already-chosen item into the learner's language, and nothing else.
 *
 * Exists because the barrier's answer to "this translation is in the wrong language" must be
 * "translate it again", not "generate a different item". The collection generator would return a
 * different word; the example regenerator would return a different sentence. Both lose work that
 * was correct — and, on a term that already has distractors, both invalidate them.
 *
 * A transport/API failure throws: the caller counts an attempt and decides whether to retry.
 */
interface TranslationRepairPort
{
    public function repair(TranslationRepairBrief $brief): RepairedTranslation;
}
