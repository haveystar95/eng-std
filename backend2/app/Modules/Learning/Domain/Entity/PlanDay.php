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
 * ## Two attempts, then stop — and since v0.3 that is also two PAID CALLS
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
 * one claim is one call, and this number is the money.
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
    ): self {
        return new self(
            $id, $planId, $dayIndex, $kind, $collectionId, $title, $outcomeText, $skills,
            $roleBrief, $scheduledOn, $status, $generationAttempts, $failReason, $lastViolations,
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

    public function markReady(CollectionId $collectionId): void
    {
        if ($this->status !== PlanDayStatus::Generating) {
            throw InvalidPlanTransition::forDay($this->status, 'объявить день готовым');
        }

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
     * `$paidCalls` is how many model calls this run actually made — one for an ordinary day, two
     * when the answer was nearly right and a repair call was spent on it
     * ({@see \App\Modules\Generation\Application\Service\PlanDayRepairer}). The counter is the
     * MONEY (п. 199, первая половина), so the second call is charged here rather than staying
     * invisible: a day that spent a repair has spent its budget, and there is no third answer to
     * buy with it.
     *
     * @param  list<string>  $violations  the verdict as ADDRESSES, one line per check
     */
    public function markFailed(string $reason, array $violations = [], int $paidCalls = 1): void
    {
        $this->failReason = mb_substr(trim($reason), 0, 500);
        $this->generationAttempts += max(0, $paidCalls - 1);
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
