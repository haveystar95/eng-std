<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Exception\InvalidPlanOutline;

/**
 * P1's answer, typed.
 *
 * Built from the decoded JSON exactly once, at the boundary, so nothing downstream ever does
 * `$outline['scenes'][0]['skills'][1]['checkpoint'] ?? null`. The three lists the whole plan is
 * bound by — `entities`, `constraints`, `goal_terms` — travel with it into every day's generation
 * and are facts about the learner's situation, not suggestions.
 *
 * ## v0.2: scenes and priced skills, and no days at all
 *
 * The model no longer answers in days and is no longer told how many there are. It answers in
 * SCENES, each holding ordered SKILLS, each skill priced by the model in `est_terms`. Everything
 * about the calendar — how many days, which skill lands on which, what does not fit — is computed
 * from this by {@see \App\Modules\Learning\Domain\Service\PlanScheduler}.
 *
 * There is also no `final_day` any more, and its absence is the same fix one step further: v0 asked
 * the model for the final day's checkpoints and the list drifted from the days it was copying; v0.1
 * kept asking for its title. The title is now a constant in the plan's support language and the
 * checkpoints are {@see finalCheckpoints()} — so nothing about the rehearsal is a model opinion.
 *
 * The CONTENT judgement (are there scenes at all, does every skill have one checkpoint, is
 * `est_terms` in range) is not made here: it is made once, by
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
     * @param  list<PlanScene>  $scenes
     */
    public function __construct(
        public string $title,
        public string $goalRestated,
        public array $entities,
        public array $constraints,
        public array $goalTerms,
        public array $scenes,
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

        $scenes = [];
        $position = 0;
        $rawScenes = is_array($raw['scenes'] ?? null) ? $raw['scenes'] : [];
        foreach ($rawScenes as $rawScene) {
            if (! is_array($rawScene)) {
                $violations[] = 'сцена каркаса — не объект';

                continue;
            }

            $sceneIndex = count($scenes) + 1;
            $skills = [];
            $rawSkills = is_array($rawScene['skills'] ?? null) ? $rawScene['skills'] : [];
            foreach ($rawSkills as $rawSkill) {
                if (! is_array($rawSkill)) {
                    continue;
                }
                $outcome = self::text($rawSkill['outcome'] ?? '');
                if ($outcome === '') {
                    continue;
                }

                $skills[] = new PlanSkill(
                    outcome: $outcome,
                    checkpoint: self::text($rawSkill['checkpoint'] ?? ''),
                    // A skill whose price did not survive the round trip is worth ONE term rather
                    // than nothing: an ability the scheduler thinks is free is worse than one it
                    // underprices, because free abilities never make a plan «не влезает».
                    estTerms: max(1, (int) self::scalar($rawSkill['est_terms'] ?? 0)),
                    sceneIndex: $sceneIndex,
                    skillIndex: count($skills),
                    position: $position++,
                    topics: self::stringList($rawSkill['topics'] ?? null),
                );
            }

            if ($skills === []) {
                $violations[] = 'сцена без единого умения';

                continue;
            }

            $scenes[] = new PlanScene(
                index: $sceneIndex,
                title: self::text($rawScene['title'] ?? ''),
                role: self::role($rawScene['role'] ?? null),
                skills: $skills,
            );
        }

        if ($scenes === []) {
            $violations[] = 'в каркасе нет ни одной сцены';
        }

        if ($violations !== []) {
            throw InvalidPlanOutline::because($violations);
        }

        return new self(
            title: self::text($raw['title'] ?? ''),
            goalRestated: self::text($raw['goal_restated'] ?? ''),
            entities: self::entities($raw['entities'] ?? null),
            constraints: self::stringList($raw['constraints'] ?? null),
            goalTerms: self::stringList($raw['goal_terms'] ?? null),
            scenes: $scenes,
        );
    }

    /**
     * Every ability of the whole plan, in P1's own order: scene 1's abilities, then scene 2's, and
     * so on. The order is load-bearing — the prompt writes scenes and skills by dependency and by
     * likelihood, so when a plan does not fit, the scheduler drops from the END.
     *
     * @return list<PlanSkill>
     */
    public function skills(): array
    {
        $out = [];
        foreach ($this->scenes as $scene) {
            foreach ($scene->skills as $skill) {
                $out[] = $skill;
            }
        }

        return $out;
    }

    public function scene(int $index): ?PlanScene
    {
        foreach ($this->scenes as $scene) {
            if ($scene->index === $index) {
                return $scene;
            }
        }

        return null;
    }

    /**
     * The FINAL day's checkpoint list: every checkpoint of the plan, in P1's order.
     *
     * Assembled here and never asked of the model. v0 asked, and the answer drifted from the days
     * it was supposed to be a copy of — promising the learner an exam harder than the plan they
     * took (docs/research/plan-sandbox-2026-08-29.md §7.7).
     *
     * Unlike v0.1 this now includes the checkpoints of scenes with NO interlocutor: the checkpoints
     * used to hang off the role, so a scene without one silently contributed nothing to the
     * rehearsal. An ability nobody watches is still an ability the plan promised.
     *
     * @return list<string>
     */
    public function finalCheckpoints(): array
    {
        $out = [];
        foreach ($this->skills() as $skill) {
            if ($skill->checkpoint !== '') {
                $out[] = $skill->checkpoint;
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
