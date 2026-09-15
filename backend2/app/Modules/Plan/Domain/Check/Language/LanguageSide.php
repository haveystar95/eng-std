<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/** Which language of the pair a rule reads: the lesson's target, or the learner's own. */
enum LanguageSide: string
{
    case Target = 'target';
    case Native = 'native';
}
