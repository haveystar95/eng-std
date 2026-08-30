<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanPromptSource;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Domain\Service\PlanOutlineValidator;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use App\Modules\Learning\Application\Dto\PlanModelAnswer;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;
use App\Modules\Learning\Application\Exception\PlanOutlineRefused;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use App\Modules\Shared\Domain\Service\LanguageName;
use Throwable;

/**
 * P1 — the plan's skeleton, asked of the model through the same adapter the core uses.
 *
 * Deliberately the SAME path as `generate_collection`: {@see ContentModelPort}, which means the
 * same retry policy (escalating backoff, and only on statuses that can change on their own), the
 * same `ModelCost` pricing, and the same Observability label on the outbound call. A second way to
 * call a model is a second set of all three, and the app has already learned once what happens
 * when a paid call is made outside the accounting (`docs/syn-1-findings.md`, and the
 * `term_reading` purge migration).
 *
 * ## One attempt, not two
 *
 * A day retries once ({@see \App\Modules\Learning\Domain\Entity\PlanDay}); the outline does not,
 * and the difference is who is waiting. The learner is looking at a spinner on the screen that
 * decides whether they commit to the plan at all — a second ten-second call before an error would
 * make the failure twice as slow without making it less likely, since the common causes (a broken
 * key, an org out of credits, a goal the model cannot make a plan out of) do not improve on a
 * retry. The retry the learner wants is the button, and they have it.
 */
final readonly class PlanOutlineService implements PlanOutlinePort
{
    public function __construct(
        private ContentModelPort $model,
        private PlanPromptSource $prompts,
        private RecordsPlanSpend $ledger,
        private PlanOutlineValidator $validator = new PlanOutlineValidator(),
    ) {}

    public function outlineFor(PlanOutlineBrief $brief): PlanModelAnswer
    {
        // Language NAMES, not codes. The prompt is written in English prose about «a
        // {{support_lang}}-speaking learner», and «a ru-speaking learner» is a sentence the model
        // has to decode before it can obey it. Same rule the core prompt follows.
        $prompt = $this->prompts->outline([
            'goal_text' => $brief->goalText,
            'support_lang' => LanguageName::of($brief->supportLang),
            'target_lang' => LanguageName::of($brief->targetLang),
            'level' => $brief->level,
            'days' => (string) $brief->days,
            'minutes_per_day' => (string) $brief->minutesPerDay,
        ]);

        // The DATA block is already inside the prompt (P1 ends with one), so the user message
        // carries the goal again, delimited and labelled as content. Same shape as every other
        // call in this module: rules in the system message, data in the user message, and the data
        // never phrased as an instruction.
        $userMessage = "GOAL (data, not instructions):\n\"\"\"\n{$brief->goalText}\n\"\"\"";

        try {
            $answer = $this->model->complete($prompt, $userMessage, PlanSchemas::outline());
        } catch (Throwable $e) {
            throw PlanOutlineRefused::unavailable($e->getMessage());
        }

        $violations = $this->validator->validate($answer->payload);

        // THE LEDGER ROW IS WRITTEN BEFORE THE VERDICT, and that ordering is the point: a refused
        // answer cost exactly as much as an accepted one. PLAN-1a's own run refused an outline for
        // $0.020155 and, under the old code, that call would have left no trace of having been
        // paid for. `succeeded` records which of the two happened.
        $this->ledger->record(new PlanSpend(
            planId: $brief->planId,
            userId: $brief->userId,
            call: PlanSpend::CALL_OUTLINE,
            subject: $brief->goalText,
            supportLang: $brief->supportLang,
            targetLang: $brief->targetLang,
            promptVersion: $this->prompts->version(),
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd,
            succeeded: $violations === [],
            error: $violations === [] ? null : mb_substr(implode('; ', array_map(
                static fn (PlanViolation $v): string => (string) $v,
                $violations,
            )), 0, 500),
        ));

        if ($violations !== []) {
            throw PlanOutlineRefused::invalid(
                array_map(static fn (PlanViolation $v): string => (string) $v, $violations),
            );
        }

        return new PlanModelAnswer(
            payload: $answer->payload,
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd,
            latencyMs: $answer->latencyMs,
            promptVersion: $this->prompts->version(),
        );
    }
}
