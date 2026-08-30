<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\PlanRehearsalLineView;
use App\Modules\Learning\Application\Dto\PlanRehearsalView;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;

/**
 * «Быстрая репетиция перед событием» — кадр 1c · 15.
 *
 * Every PHRASE the plan taught, in day order, each with the line the interlocutor says before it.
 * Words are deliberately absent: the screen is three minutes long, and what three minutes buy is
 * hearing yourself say the sentences, not being tested on the vocabulary inside them.
 *
 * It reads through {@see PlanProgress} like every other plan surface — the same collection and
 * content reads, and therefore the same answer about what the day actually holds. It ignores the
 * standings it gets back: a rehearsal is not a session, it schedules nothing and closes no stage,
 * so where a word stands is not its business.
 */
final readonly class GetPlanRehearsalHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
    ) {}

    public function __invoke(GetPlanRehearsal $query): ?PlanRehearsalView
    {
        $plan = $this->plans->findById(PlanId::fromString($query->planId));
        if ($plan === null || ! $plan->userId()->equals($query->actorId)) {
            return null;
        }

        $days = $this->days->listForPlan($plan->id());
        $progress = $this->progress->forPlan($plan, $days);

        /** @var array<int, PlanDay> $byIndex */
        $byIndex = [];
        foreach ($days as $day) {
            $byIndex[$day->dayIndex()] = $day;
        }

        $lines = [];
        // Day order, and inside a day the order the collection holds — which is the order the day
        // itself taught them in. A rehearsal that shuffled would be a different situation.
        $indexes = array_keys($progress->days);
        sort($indexes);

        foreach ($indexes as $index) {
            $dayProgress = $progress->days[$index];
            [$role, $cue] = $this->roleOf($byIndex[$index] ?? null);

            foreach ($dayProgress->termIds as $termId) {
                $content = $dayProgress->content[$termId] ?? null;
                // Phrases only. `word` is the one type this screen has no use for.
                if ($content === null || $content->type === 'word') {
                    continue;
                }

                $lines[] = new PlanRehearsalLineView(
                    termId: $termId,
                    text: $content->text,
                    translation: $content->translation,
                    cue: $cue,
                    role: $role,
                    dayIndex: $index,
                );
            }
        }

        return new PlanRehearsalView($plan->id()->value, $plan->title(), $lines);
    }

    /**
     * The day's interlocutor and the first thing they say.
     *
     * `role.opening_lines` is the ONE place in the whole outline that holds real utterances by the
     * other side ({@see plan_outline.v0.1.1.md}); everything else there is описание. So it is the
     * only honest source for «Врач спросит: …», and a day with no role gets no cue rather than an
     * invented one.
     *
     * @return array{0: ?string, 1: ?string} name, first opening line
     */
    private function roleOf(?PlanDay $day): array
    {
        $role = $day?->roleBrief()['role'] ?? null;
        if (! is_array($role)) {
            return [null, null];
        }

        $name = is_string($role['name'] ?? null) ? $role['name'] : null;
        $lines = $role['opening_lines'] ?? null;
        $first = is_array($lines) ? ($lines[0] ?? null) : null;
        $cue = is_array($first) && is_string($first['text'] ?? null) ? $first['text'] : null;

        return [$name, $cue];
    }
}
