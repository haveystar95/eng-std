<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Exception\InvalidPlanOutline;

/**
 * P1's answer, typed.
 *
 * Built from the decoded JSON exactly once, at the boundary, so nothing downstream ever does
 * `$outline['days'][0]['role']['checkpoints'][1] ?? null`. The three lists the whole plan is bound
 * by — `entities`, `constraints`, `goal_terms` — travel with it into every day's generation and are
 * facts about the learner's situation, not suggestions.
 *
 * The CONTENT judgement (are there enough checkpoints, does each one match an outcome, is the
 * coverage honest) is not made here: it is made once, by
 * {@see \App\Modules\Generation\Domain\Service\PlanOutlineValidator}, on the model's raw answer
 * before it is ever stored. This type is the STRUCTURAL parse and it refuses only what it cannot
 * represent — which is what makes it safe to re-read a stored outline written months ago.
 */
final readonly class PlanOutline
{
    /**
     * @param  list<array{name: string, gender: string, number: string, note: string}>  $entities
     * @param  list<string>  $constraints
     * @param  list<string>  $goalTerms
     * @param  list<PlanOutlineDay>  $days
     */
    public function __construct(
        public string $title,
        public string $goalRestated,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        public array $days,
        public string $finalDayTitle,
    ) {}

    /**
     * Parse a stored / freshly returned outline.
     *
     * @param  array<mixed>  $raw
     *
     * @throws InvalidPlanOutline when the shape cannot be represented at all
     */
    public static function fromArray(array $raw): self
    {
        $violations = [];

        $days = [];
        $rawDays = is_array($raw['days'] ?? null) ? $raw['days'] : [];
        foreach ($rawDays as $rawDay) {
            if (! is_array($rawDay)) {
                $violations[] = 'день каркаса — не объект';

                continue;
            }

            $outcome = self::stringList($rawDay['outcome'] ?? null);
            if ($outcome === []) {
                $violations[] = 'день без единого умения (`outcome`)';

                continue;
            }

            $days[] = new PlanOutlineDay(
                index: (int) self::scalar($rawDay['index'] ?? count($days) + 1),
                title: self::text($rawDay['title'] ?? ''),
                // A day whose budget did not survive is priced at one term per ability rather than
                // at zero: an ability the scheduler thinks is free is worse than one it overprices.
                termBudget: max(count($outcome), (int) self::scalar($rawDay['term_budget'] ?? 0)),
                outcome: $outcome,
                role: self::role($rawDay['role'] ?? null),
                topics: self::stringList($rawDay['topics'] ?? null),
            );
        }

        if ($days === []) {
            $violations[] = 'в каркасе нет ни одного дня знакомства';
        }

        if ($violations !== []) {
            throw InvalidPlanOutline::because($violations);
        }

        $finalDay = is_array($raw['final_day'] ?? null) ? $raw['final_day'] : [];

        return new self(
            title: self::text($raw['title'] ?? ''),
            goalRestated: self::text($raw['goal_restated'] ?? ''),
            entities: self::entities($raw['entities'] ?? null),
            constraints: self::stringList($raw['constraints'] ?? null),
            goalTerms: self::stringList($raw['goal_terms'] ?? null),
            days: $days,
            finalDayTitle: self::text($finalDay['title'] ?? ''),
        );
    }

    /**
     * Every ability of the whole plan, in P1's own order: day 1's abilities, then day 2's, and so
     * on. The order is load-bearing — it is a DEPENDENCY order (day 2 may lean on day 1 and never
     * the other way round), so when a plan does not fit, the scheduler drops from the END.
     *
     * @return list<PlanSkill>
     */
    public function skills(): array
    {
        $out = [];
        foreach ($this->days as $day) {
            foreach ($day->skills() as $skill) {
                $out[] = $skill;
            }
        }

        return $out;
    }

    public function day(int $index): ?PlanOutlineDay
    {
        foreach ($this->days as $day) {
            if ($day->index === $index) {
                return $day;
            }
        }

        return null;
    }

    /**
     * The FINAL day's checkpoint list: every checkpoint of every day, in day order.
     *
     * Assembled here and never asked of the model. v0 asked, and the answer drifted from the days
     * it was supposed to be a copy of — promising the learner an exam harder than the plan they
     * took (docs/research/plan-sandbox-2026-08-29.md §7.7).
     *
     * @return list<string>
     */
    public function finalCheckpoints(): array
    {
        $out = [];
        foreach ($this->days as $day) {
            if ($day->role === null) {
                continue;
            }
            foreach ($day->role->checkpoints as $checkpoint) {
                $out[] = $checkpoint;
            }
        }

        return $out;
    }

    private static function role(mixed $raw): ?PlanRole
    {
        if (! is_array($raw)) {
            return null;
        }

        $lines = [];
        $rawLines = is_array($raw['opening_lines'] ?? null) ? $raw['opening_lines'] : [];
        foreach ($rawLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'text' => self::text($line['text'] ?? ''),
                'translation' => self::text($line['translation'] ?? ''),
            ];
        }

        return new PlanRole(
            name: self::text($raw['name'] ?? ''),
            openingLines: $lines,
            checkpoints: self::stringList($raw['checkpoints'] ?? null),
            ifSilent: self::text($raw['if_silent'] ?? ''),
        );
    }

    /** @return list<array{name: string, gender: string, number: string, note: string}> */
    private static function entities(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entity) {
            if (! is_array($entity)) {
                continue;
            }
            $name = self::text($entity['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'gender' => self::text($entity['gender'] ?? 'none'),
                'number' => self::text($entity['number'] ?? 'singular'),
                'note' => self::text($entity['note'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    private static function stringList(mixed $raw): array
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

    private static function scalar(mixed $raw): string|int|float|bool
    {
        return is_scalar($raw) ? $raw : 0;
    }
}
