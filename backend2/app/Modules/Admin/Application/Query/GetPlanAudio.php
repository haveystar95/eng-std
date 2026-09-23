<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** A sound of the plan — a scene's line or a talk's line — for «послушать» on the plan page (наряд ADM-1). */
final readonly class GetPlanAudio
{
    public function __construct(public string $code, public string $audioId) {}
}
