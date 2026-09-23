<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection\Check;

use App\Modules\Plan\Domain\Inspection\PlanCheck;
use App\Modules\Plan\Domain\Inspection\PlanFacts;
use App\Modules\Plan\Domain\Inspection\PlanIssue;

/**
 * ВЫЗОВ МОДЕЛИ LOST (DECISIONS п. 334): no answer came — our wait ran out or the process died on the call — and the vendor
 * may still have billed it. Never retried by anyone; the operator must know it happened.
 */
final class LostModelCall implements PlanCheck
{
    public const CODE = 'lost_model_call';

    public function code(): string
    {
        return self::CODE;
    }

    public function find(PlanFacts $facts): array
    {
        $out = [];
        foreach ($facts->calls as $call) {
            if ($call->status !== 'lost') {
                continue;
            }
            $out[] = new PlanIssue(self::CODE, PlanIssue::ERROR, $call->day, 'call', $call->id,
                'Вызов модели'.($call->purpose === null ? '' : " ({$call->purpose})").' без ответа — lost'.($call->error === null ? '' : ": {$call->error}"),
                ['purpose' => $call->purpose],
            );
        }

        return $out;
    }
}
