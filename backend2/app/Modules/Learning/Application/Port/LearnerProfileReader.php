<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeZone;

/**
 * The slice of the user's Identity profile that Learning needs — narrow on purpose, so Learning
 * never grows a dependency on the whole profile.
 */
interface LearnerProfileReader
{
    public function cefrLevelFor(UserId $user): CefrLevel;

    /**
     * The user's daily new-term quota — one global cap, not per collection. Zero means "reviews
     * only, introduce nothing new"; clamped to a sane maximum so a single session can't seed the
     * whole dictionary.
     */
    public function newTermsPerDay(UserId $user): int;

    /**
     * The user's calendar timezone — the scheduler rounds day-scale due dates down to the start of
     * the user's day in it (device-batch F19). Falls back to UTC when unknown or unparseable.
     */
    public function timezoneFor(UserId $user): DateTimeZone;

    /**
     * The learner's own language (`profiles.native_language`) — which of a term's translations is
     * the QUESTION on their card. A term is global and accumulates translations in several
     * languages, so every content read has to name one; without it the reader returned whichever
     * row sorted first, and a Russian speaker could be asked in Ukrainian.
     *
     * Falls back to ru (the column's own default) rather than returning null: a card has to be
     * built, and every profile in this app has ru.
     */
    public function nativeLangFor(UserId $user): string;
}
