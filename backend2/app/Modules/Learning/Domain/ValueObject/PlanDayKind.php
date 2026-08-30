<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

enum PlanDayKind: string
{
    /** A day that teaches new terms and owns a collection. */
    case Intro = 'intro';

    /**
     * The last day. Introduces NOTHING and owns no collection — practice plus one conversation
     * that runs through every checkpoint of the whole plan. The absence of a collection is the
     * schema saying so, rather than an empty collection pretending to be a day.
     */
    case Final = 'final';
}
