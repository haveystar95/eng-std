<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * A first-pass swipe judgement. Three verdicts, not two — a binary choice pushes people to
 * lie toward "known". The projection onto progress lives in TriageTermsHandler.
 */
enum TriageVerdict: string
{
    case Known = 'known';     // → state=known (awaits a verification check)
    case Unknown = 'unknown'; // → stays new (full intro via multiple_choice)
    case Unsure = 'unsure';   // → state=learning (skips the intro recognition step)
}
