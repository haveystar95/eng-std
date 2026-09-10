<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** A real interaction of the event, or a variant of the core one (same place, other complication). */
enum SceneKind: string
{
    case Situation = 'situation';
    case Variant = 'variant';
}
