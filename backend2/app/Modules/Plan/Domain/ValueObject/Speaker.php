<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** Who a voiced line belongs to: the partner (speaker A) or the learner (B — and their phrases and words). */
enum Speaker: string
{
    case Partner = 'partner';
    case Learner = 'learner';
}
