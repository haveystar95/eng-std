<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\PlanDayGenerationBrief;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\SceneDay;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * The idempotency gate for a paid call.
 *
 * The claim and the read of the brief happen in ONE transaction, over a locked day row. Two workers
 * handed the same day — a replayed dispatch, a restart mid-job, a learner who tapped twice — means
 * one of them gets a brief and the other gets null and returns. Without the lock both would read
 * `pending`, both would write `generating`, and the plan would pay twice for one day.
 *
 * Returns null, never throws, for every «nothing to do» case: the day is already ready, somebody
 * else has it, both attempts are spent, it is the final day, the plan is not running. A job that
 * threw on those would fill the failed-jobs table with successful outcomes.
 *
 * ## What the brief carries since v0.4: a SCENE, and no arithmetic
 *
 * The three counts this handler used to compute ({@see \App\Modules\Learning\Domain\Service\DayCapacity::split()})
 * are gone with the arrays they sized. What it reads instead is the scene the scheduler stored on
 * the day when the plan was made — its title, its вводка, its skills WITH IDS, the lines the other
 * person opens with, the names of the scenario — and hands it over whole. The day is generated from
 * ONE row and never from a join, which is what makes «what does this day promise» a question with
 * one answer even after the plan has been rescheduled.
 */
final readonly class ClaimPlanDayHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private TransactionManager $tx,
    ) {}

    public function __invoke(ClaimPlanDay $command): ?PlanDayGenerationBrief
    {
        return $this->tx->run(function () use ($command): ?PlanDayGenerationBrief {
            $plan = $this->plans->findById(PlanId::fromString($command->planId));
            if ($plan === null || ! $plan->status()->generates()) {
                return null;
            }

            $day = $this->days->findByIndexForUpdate($plan->id(), $command->dayIndex);
            if ($day === null || ! $day->claim()) {
                return null;
            }
            $this->days->save($day);

            $outline = $plan->parsedOutline();
            if ($outline === null) {
                return null;
            }

            $stored = $day->roleBrief() ?? [];
            $scene = is_array($stored['scene'] ?? null) ? $stored['scene'] : [];
            // The PLAN's language, not the account's — see LearningPlan::$supportLang.
            $support = $plan->supportLang();

            return new PlanDayGenerationBrief(
                planId: $plan->id()->value,
                userId: $plan->userId()->value,
                planDayId: $day->id()->value,
                dayIndex: $day->dayIndex(),
                planTitle: $plan->title(),
                goalText: $plan->goalText(),
                dayTitle: $day->title(),
                supportLang: $support->value,
                targetLang: $plan->targetLang()->value,
                level: $plan->level()->value,
                // What the plan SAID this day would hold, back when it was scheduled. Not a demand
                // on the model — the shelves are sized by the prompt's guides and counted as
                // warnings — but the number the preview promised and the readiness denominator uses.
                termBudget: is_int($stored['term_budget'] ?? null) ? $stored['term_budget'] : SceneDay::UNITS,
                sceneTitle: self::text($scene['title'] ?? null) ?: $day->title(),
                sceneIntro: self::text($scene['intro'] ?? ($stored['intro'] ?? null)),
                skills: self::skillsOf($scene, $stored),
                // The interlocutor's own lines: raw material for «Тебе скажут». v0.4 asks the day to
                // ADAPT them to the scene rather than quote them, so nothing is compared against
                // this list any more — but a shelf written without it is a conversation with
                // somebody else.
                openingLines: self::openingLines($scene, $stored),
                entities: self::strings($scene['entities'] ?? null) ?: $outline->entities,
                goalTerms: $outline->goalTerms,
                // WHERE THE LAST ANSWER FOR THIS DAY BROKE, as addresses. Read here because the
                // claim is what has the day row open, and handed over as strings so Generation is
                // not asked to reconstruct Learning's rows.
                previousViolations: $day->lastViolations(),
            );
        });
    }

    /**
     * The scene's abilities, each with the id its cards must name.
     *
     * Two shapes are read and both are this plan's own history: `scene.skills` is what a v0.4 day
     * stores, `skills` at the top level is what every day written before it stored. A skill with no
     * id gets one by position — the same rule {@see \App\Modules\Learning\Domain\ValueObject\PlanOutline}
     * applies to the model's answer, so a day scheduled under v0.2 and generated today still hands
     * the prompt something `skill_ref` can point at.
     *
     * @param  array<mixed>  $scene
     * @param  array<mixed>  $stored
     * @return list<array{id: string, outcome: string, checkpoint: string, topics: list<string>}>
     */
    private static function skillsOf(array $scene, array $stored): array
    {
        $raw = is_array($scene['skills'] ?? null) ? $scene['skills'] : ($stored['skills'] ?? null);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $skill) {
            if (! is_array($skill)) {
                continue;
            }
            $outcome = self::text($skill['outcome'] ?? null);
            if ($outcome === '') {
                continue;
            }

            $out[] = [
                'id' => self::text($skill['id'] ?? null) ?: 's' . (count($out) + 1),
                'outcome' => $outcome,
                'checkpoint' => self::text($skill['checkpoint'] ?? null),
                'topics' => self::strings($skill['topics'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * What every interlocutor of this day says — from the scene, or from the role brief a day
     * written before v0.4 stored.
     *
     * @param  array<mixed>  $scene
     * @param  array<mixed>  $stored
     * @return list<string>
     */
    private static function openingLines(array $scene, array $stored): array
    {
        $direct = self::strings($scene['opening_lines'] ?? null);
        if ($direct !== []) {
            return $direct;
        }

        $role = $stored['role'] ?? null;
        $lines = is_array($role) ? ($role['opening_lines'] ?? null) : null;
        if (! is_array($lines)) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            $text = is_array($line) ? self::text($line['text'] ?? null) : self::text($line);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function strings(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $text = self::text($item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    private static function text(mixed $raw): string
    {
        return is_scalar($raw) ? trim((string) $raw) : '';
    }
}
