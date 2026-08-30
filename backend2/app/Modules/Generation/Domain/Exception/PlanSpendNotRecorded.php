<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * The money was spent and the ledger would not take the row.
 *
 * THIS ONE IS NEVER SWALLOWED, and it is the only thing in the plan pipeline that is not. Every
 * other failure here is caught, written onto the day as a `fail_reason` and turned into a state a
 * person can look at — because a day that did not generate is a product problem with a product
 * answer. An unrecorded payment is not: the call already happened, the money is already gone, and
 * the one thing that must not happen next is for the rest of the pipeline to succeed and make it
 * look like nothing did.
 *
 * The rule was bought, not reasoned into existence. PLAN-1a's live run made three paid `gpt-5.4`
 * calls whose log rows were refused by a CHECK that had not been migrated, and the refusal was
 * caught and dropped one layer down, exactly as designed for a LOG. The plan then completed
 * successfully. Nothing anywhere said the accounting had failed, and the three calls are gone —
 * see `docs/plan-1a-run.md`. A ledger that can fail quietly is not a ledger.
 *
 * So: error-level log at the writer, and this exception all the way out. On the day path it
 * escapes the handler's own try/catch deliberately ({@see
 * \App\Modules\Generation\Application\Command\GeneratePlanDayHandler}), so the job fails, the row
 * lands in `failed_jobs`, and somebody finds out on the same day rather than at the next audit.
 */
final class PlanSpendNotRecorded extends RuntimeException
{
    private function __construct(
        public readonly string $planId,
        public readonly ?string $costUsd,
        string $message,
        ?Throwable $previous,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function forPlan(string $planId, ?string $costUsd, Throwable $previous): self
    {
        $spent = $costUsd ?? 'неизвестно сколько';

        return new self(
            $planId,
            $costUsd,
            "Вызов модели для плана {$planId} состоялся и стоил {$spent}, но строка учёта не "
            . 'записалась: ' . $previous->getMessage() . '. Это не проглатывается: неучтённый '
            . 'платёж должен ронять работу, а не оставаться незамеченным.',
            $previous,
        );
    }
}
