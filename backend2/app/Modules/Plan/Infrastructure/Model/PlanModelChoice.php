<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Model;

/**
 * THE MODEL OF ONE PURPOSE (наряд GEN-4, `plan.model.purposes`): its name at the vendor and the reasoning effort it is asked
 * with — null sends none, the model's own default.
 */
final readonly class PlanModelChoice
{
    public function __construct(
        public string $model,
        public ?string $reasoningEffort = null,
    ) {}
}
