<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\Exception\InvalidPlanTransition;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use DateTimeImmutable;

/**
 * One day of a plan, and the small state machine that keeps a paid model call from being made
 * twice.
 *
 * ## Two attempts, then stop — and the attempt is the DAY CALL
 *
 * `generationAttempts` counts CLAIMS, not failures, and the cap is two. The reason it is a stored
 * counter and not a queue `tries` setting: the queue retries a job that CRASHED, which is a
 * different event from a day whose material came back and did not pass the validator. The second
 * costs the same money as the first and will keep costing it, so it stops after one re-run and says
 * why in `failReason`. A day that failed twice is a thing a person looks at.
 *
 * Until v0.3 this counter did not mean what it says. `PlanDayComposer` made its own second call
 * inside one claim, so two claims were FOUR paid calls — a live day spent $0.197 on a budget that
 * was written for two (`docs/research/plan-v0.2.1-run.md`). The composer's inner retry is gone;
 * one claim is one day call.
 *
 * ## The repair is money too, and it is counted in its OWN column
 *
 * v0.3.1 added P2R and charged it to this same counter, on the failed path only. The E2E-SIM-1 run
 * showed both ends of what that did (Д-18): a day that spent P2 + P2R and was WRITTEN said
 * «1 attempt», and a day that spent P2 + P2 + P2R and was REFUSED said «3 attempts» — three, under
 * a cap of two, having made exactly the two day calls the cap allows. The same two calls read as
 * one number or as two depending on how the day ended.
 *
 * So {@see $repairCalls} stands beside it, charged identically on both paths, and this counter is
 * the DAY calls alone — the only thing {@see MAX_ATTEMPTS} caps and the only thing that decides
 * `failed`. Money is {@see paidCalls()}, which is the sum, and the ledger rows in
 * `generation_requests` remain the record of what each call actually cost.
 *
 * ## What a failed attempt leaves behind — the LAST verdict, and only as addresses
 *
 * {@see lastViolations()} — what the previous answer failed, as `phrases[3].translation — code:
 * reason` and nothing else. It goes into the next attempt's prompt as data. `failReason` stays what
 * it was — a sentence for the person reading the plan screen, truncated to what a column holds.
 *
 * It ACCUMULATED across attempts for one commit, and the live run refuted that (п. 199, вторая
 * половина, отменена 31.08). The hypothesis was sound and its first test looked like a triumph: the
 * second answer, told all twelve of the first answer's defects, fixed all twelve. The third answer
 * was told thirteen — and the list quoted the FIRST attempt's sentences, so the third answer
 * returned those sentences, complete with the defects attempt two had already fixed
 * (`docs/research/plan-v0.3-run.md`, второй заход).
 *
 * What was wrong was never the accumulation as such; it was that the list carried the model's own
 * text back to it. Both halves are dealt with here and in {@see PlanViolation::address()}: the list
 * is the LAST attempt's, and it carries addresses rather than sentences.
 *
 * ## Idempotent by (plan, day)
 *
 * {@see claim()} refuses a day that is already `generating` or already finished, and the repository
 * claims it inside a locked read. Two workers handed the same day — a replayed dispatch, a restart
 * mid-job — means one of them pays and the other returns.
 */
final class PlanDay
{
    public const MAX_ATTEMPTS = 2;

    /**
     * How many REPAIR calls one day may cost — one per run, and there are two runs
     * ({@see \App\Modules\Generation\Application\Service\PlanDayRepairer}: «one repair call, and
     * never two»). Structurally guaranteed rather than gated, so this is the assertion that the
     * structure held, not a second budget with its own opinion.
     */
    public const MAX_REPAIR_CALLS = 2;

    private function __construct(
        private readonly PlanDayId $id,
        private readonly PlanId $planId,
        private readonly int $dayIndex,
        private readonly PlanDayKind $kind,
        private ?CollectionId $collectionId,
        private readonly string $title,
        private readonly ?string $outcomeText,
        /** @var list<array<string, mixed>> */
        private readonly array $skills,
        /** @var array<string, mixed>|null */
        private readonly ?array $roleBrief,
        private readonly ?DateTimeImmutable $scheduledOn,
        private PlanDayStatus $status,
        private int $generationAttempts,
        private ?string $failReason,
        /** @var list<string> what the LAST answer failed, as addresses — {@see markFailed()} */
        private array $lastViolations = [],
        /** {@see chargeRepairs()} — the P2R calls, counted apart from the P2 ones. */
        private int $repairCalls = 0,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $skills
     * @param  array<string, mixed>|null  $roleBrief
     */
    public static function plan(
        PlanDayId $id,
        PlanId $planId,
        int $dayIndex,
        PlanDayKind $kind,
        string $title,
        ?string $outcomeText,
        array $skills,
        ?array $roleBrief,
        ?DateTimeImmutable $scheduledOn,
    ): self {
        return new self(
            $id, $planId, $dayIndex, $kind, null, $title, $outcomeText, $skills, $roleBrief,
            $scheduledOn, PlanDayStatus::Pending, 0, null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     * @param  array<string, mixed>|null  $roleBrief
     * @param  list<string>  $lastViolations
     */
    public static function reconstitute(
        PlanDayId $id,
        PlanId $planId,
        int $dayIndex,
        PlanDayKind $kind,
        ?CollectionId $collectionId,
        string $title,
        ?string $outcomeText,
        array $skills,
        ?array $roleBrief,
        ?DateTimeImmutable $scheduledOn,
        PlanDayStatus $status,
        int $generationAttempts,
        ?string $failReason,
        array $lastViolations = [],
        int $repairCalls = 0,
    ): self {
        return new self(
            $id, $planId, $dayIndex, $kind, $collectionId, $title, $outcomeText, $skills,
            $roleBrief, $scheduledOn, $status, $generationAttempts, $failReason, $lastViolations,
            $repairCalls,
        );
    }

    /**
     * Take this day for generation. Returns false when somebody else already has it or it is done —
     * the caller returns rather than paying for a second copy.
     */
    public function claim(): bool
    {
        if ($this->kind === PlanDayKind::Final) {
            // The final day introduces nothing. There is no call to make and no collection to fill.
            return false;
        }
        if ($this->status !== PlanDayStatus::Pending && $this->status !== PlanDayStatus::Failed) {
            return false;
        }
        if ($this->generationAttempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $this->status = PlanDayStatus::Generating;
        $this->generationAttempts++;
        $this->failReason = null;

        return true;
    }

    /**
     * @param  int  $repairCalls  P2R calls this run made — charged on the WRITTEN day exactly as on
     *                            the refused one ({@see chargeRepairs()}).
     */
    public function markReady(CollectionId $collectionId, int $repairCalls = 0): void
    {
        if ($this->status !== PlanDayStatus::Generating) {
            throw InvalidPlanTransition::forDay($this->status, 'объявить день готовым');
        }

        $this->chargeRepairs($repairCalls);

        $this->collectionId = $collectionId;
        $this->status = PlanDayStatus::Ready;
        $this->failReason = null;
        // The history existed to tell the NEXT attempt what to avoid, and there is no next
        // attempt. Keeping it would make a written day carry a list of things wrong with a day
        // that no longer exists.
        $this->lastViolations = [];
    }

    /**
     * The attempt did not work.
     *
     * Back to `pending` while there is an attempt left, so the next dispatch picks it up; `failed`
     * once both are spent, with the reason kept. The reason is trimmed to what a column and a human
     * can use — a stack trace in this field is a field nobody reads.
     *
     * The violations REPLACE what was there, deduplicated within the attempt: the next call is told
     * what the last answer did, not everything this day has ever done. See the class docblock for
     * the run that cost $0.05 to establish which of the two is right.
     *
     * ## THE REPAIR IS CHARGED, AND IT IS NOT AN ATTEMPT
     *
     * This used to take `$paidCalls` and add the repair to `generationAttempts`, on the reasoning
     * that the counter is the money (п. 199). The live run showed what that costs: the SAME two
     * calls read as one attempt when the day came out `ready` and as two when it came out `failed`,
     * because only this path charged them — and day 2, which made two P2 calls under a cap of two,
     * ended up saying it had made three attempts (`docs/research/e2e-sim-1.md`, Д-18).
     *
     * A counter whose meaning depends on the outcome cannot cap anything. So `generationAttempts`
     * counts DAY calls and nothing else — {@see claim()} is the only thing that moves it, and it is
     * the only thing that decides `failed`. Repairs are counted beside it, identically on both
     * paths ({@see chargeRepairs()}, {@see markReady()}). The money is the sum of the two, and it is
     * still exactly as visible as it was — more so, because it now separates «the day was asked for
     * twice» from «the day was patched».
     *
     * @param  list<string>  $violations  the verdict as ADDRESSES, one line per check
     * @param  int  $repairCalls  P2R calls this run made: 0 or 1
     */
    public function markFailed(string $reason, array $violations = [], int $repairCalls = 0): void
    {
        $this->failReason = mb_substr(trim($reason), 0, 500);
        $this->chargeRepairs($repairCalls);
        $this->status = $this->generationAttempts >= self::MAX_ATTEMPTS
            ? PlanDayStatus::Failed
            : PlanDayStatus::Pending;

        $kept = [];
        foreach ($violations as $violation) {
            $violation = trim($violation);
            if ($violation !== '' && ! in_array($violation, $kept, true)) {
                $kept[] = $violation;
            }
        }
        $this->lastViolations = $kept;
    }

    public function markDone(): void
    {
        $this->status = PlanDayStatus::Done;
    }

    /**
     * ADD THIS RUN'S REPAIR CALLS, on whichever path the run ended.
     *
     * Clamped rather than refused: the repairer makes at most one call per run and a day has at
     * most two runs, so {@see MAX_REPAIR_CALLS} is what the pipeline already guarantees. A clamp
     * keeps a replayed `FinishPlanDay` from inflating a money column; a throw here would turn a
     * duplicate message into a failed job over a number nobody is spending.
     */
    private function chargeRepairs(int $repairCalls): void
    {
        $this->repairCalls = min(self::MAX_REPAIR_CALLS, $this->repairCalls + max(0, $repairCalls));
    }

    /**
     * GIVE A SPENT DAY ONE ATTEMPT BACK — an operator action, never a code path.
     *
     * Nothing in the pipeline calls this and nothing should: the two-attempt cap exists precisely
     * so that a broken prompt cannot spend a plan's budget on one day, and a handler that could
     * quietly reopen a day would be that cap with a hole in it. It exists for the one case the cap
     * was never about — the GATES changed after the day was refused, so the answer that failed
     * would pass now, and the alternative is a raw `UPDATE` against the owner's database.
     *
     * `lastViolations` are deliberately kept: the point of the re-run is that the next answer is
     * told where the last one broke. Kept and not extended — they are addresses now, and the day
     * they describe is the one the re-run is about to replace.
     *
     * @throws InvalidPlanTransition when the day is not actually spent — a `ready` day reopened
     *                               would throw its collection away, and a `pending` one needs
     *                               nothing.
     */
    public function reopenForRetry(): void
    {
        if ($this->status !== PlanDayStatus::Failed) {
            throw InvalidPlanTransition::forDay($this->status, 'вернуть дню попытку');
        }

        $this->status = PlanDayStatus::Pending;
        $this->generationAttempts = self::MAX_ATTEMPTS - 1;
        $this->failReason = null;
    }

    public function isReady(): bool
    {
        return $this->status === PlanDayStatus::Ready || $this->status === PlanDayStatus::Done;
    }

    public function id(): PlanDayId
    {
        return $this->id;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function dayIndex(): int
    {
        return $this->dayIndex;
    }

    public function kind(): PlanDayKind
    {
        return $this->kind;
    }

    public function collectionId(): ?CollectionId
    {
        return $this->collectionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function outcomeText(): ?string
    {
        return $this->outcomeText;
    }

    /** @return list<array<string, mixed>> */
    public function skills(): array
    {
        return $this->skills;
    }

    /** @return array<string, mixed>|null */
    public function roleBrief(): ?array
    {
        return $this->roleBrief;
    }

    public function scheduledOn(): ?DateTimeImmutable
    {
        return $this->scheduledOn;
    }

    public function status(): PlanDayStatus
    {
        return $this->status;
    }

    public function generationAttempts(): int
    {
        return $this->generationAttempts;
    }

    /** P2R calls this day has cost, on every run it has had. {@see chargeRepairs()} */
    public function repairCalls(): int
    {
        return $this->repairCalls;
    }

    /** What the day actually cost in model calls — the day calls plus the repairs. */
    public function paidCalls(): int
    {
        return $this->generationAttempts + $this->repairCalls;
    }

    public function failReason(): ?string
    {
        return $this->failReason;
    }

    /**
     * What the LAST answer for this day failed, as addresses — nothing that answer wrote.
     *
     * @return list<string>
     */
    public function lastViolations(): array
    {
        return $this->lastViolations;
    }
}
