<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

use App\Modules\Learning\Domain\Exception\InvalidPlanOutline;

/**
 * P1's answer, typed.
 *
 * Built from the decoded JSON exactly once, at the boundary, so nothing downstream ever does
 * `$outline['scenes'][0]['skills'][1]['checkpoint'] ?? null`.
 *
 * ## v0.4: scenes with a вводка, skills with an id, and no plan-level lists
 *
 * The skeleton is `goal_summary` + `scenes[]`, and everything else moved INTO the scene: the
 * interlocutor's lines, the names of the scenario, and the вводка the learner reads before the day.
 * The three plan-level lists v0.2 answered with are gone with it — `entities` is now the union of
 * the scenes' own names (plain strings, no gender), `constraints` and `goal_terms` are empty.
 * Nothing downstream lost a rule it was enforcing except the Russian gender-agreement check, whose
 * whole input was the gender P1 no longer answers with.
 *
 * ## READING AN OLD SKELETON IS NOT OPTIONAL
 *
 * A plan runs for days and its outline is re-parsed on every read. A learner halfway through a
 * v0.2 plan must not have it break because the code moved on, so this parse accepts BOTH shapes:
 * `goal_summary` or `title`/`goal_restated`, `opening_lines` as strings or as the old `role`
 * object's `{text, translation}` pairs, scene `entities` or the old plan-level ones. What an old
 * skeleton simply does not have is a вводка, and an empty intro is a день без вводки rather than a
 * broken plan.
 *
 * The CONTENT judgement (are there scenes at all, does every skill have one checkpoint, is
 * `est_terms` in range, is there an intro) is not made here: it is made once, by
 * {@see \App\Modules\Generation\Domain\Service\PlanOutlineValidator}, on the model's raw answer
 * before it is ever stored. This type is the STRUCTURAL parse and it refuses only what it cannot
 * represent — which is what makes it safe to re-read a skeleton written months ago.
 */
final readonly class PlanOutline
{
    /**
     * @param  list<string>  $entities     proper names of the scenario, from every scene
     * @param  list<string>  $constraints  always empty since v0.4 — kept because a stored v0.2
     *                                     skeleton has them and the plan screen still shows them
     * @param  list<string>  $goalTerms    the learner's own Latin-alphabet words, when a v0.2
     *                                     skeleton named them
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
        $entities = [];
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

                $skillIndex = count($skills);
                $skills[] = new PlanSkill(
                    // The model is asked for an id and the server writes one when it did not: an id
                    // is an ADDRESS, and «s1.2» computed from the position is exactly as stable as
                    // one the model would have written, because the position is what it addresses.
                    id: self::text($rawSkill['id'] ?? '') ?: 's' . $sceneIndex . '.' . ($skillIndex + 1),
                    outcome: $outcome,
                    checkpoint: self::text($rawSkill['checkpoint'] ?? ''),
                    // A skill whose price did not survive the round trip is worth ONE term rather
                    // than nothing: an ability the scheduler thinks is free is worse than one it
                    // underprices, because free abilities never make a plan «не влезает».
                    estTerms: max(1, (int) self::scalar($rawSkill['est_terms'] ?? 0)),
                    sceneIndex: $sceneIndex,
                    skillIndex: $skillIndex,
                    position: $position++,
                    topics: self::stringList($rawSkill['topics'] ?? null),
                );
            }

            if ($skills === []) {
                $violations[] = 'сцена без единого умения';

                continue;
            }

            $sceneEntities = self::stringList($rawScene['entities'] ?? null);
            foreach ($sceneEntities as $entity) {
                if (! in_array($entity, $entities, true)) {
                    $entities[] = $entity;
                }
            }

            $scenes[] = new PlanScene(
                index: $sceneIndex,
                title: self::text($rawScene['title'] ?? ''),
                intro: self::text($rawScene['intro'] ?? ''),
                skills: $skills,
                openingLines: self::openingLines($rawScene),
                entities: $sceneEntities,
            );
        }

        if ($scenes === []) {
            $violations[] = 'в каркасе нет ни одной сцены';
        }

        if ($violations !== []) {
            throw InvalidPlanOutline::because($violations);
        }

        // `goal_summary` is v0.4's one plan-level string and stands in for both: it is the goal in
        // one sentence, which is what the title says and what the restatement says.
        $summary = self::text($raw['goal_summary'] ?? '');

        return new self(
            title: $summary !== '' ? $summary : self::text($raw['title'] ?? ''),
            goalRestated: $summary !== '' ? $summary : self::text($raw['goal_restated'] ?? ''),
            entities: $entities !== [] ? $entities : self::legacyEntityNames($raw['entities'] ?? null),
            constraints: self::stringList($raw['constraints'] ?? null),
            goalTerms: self::stringList($raw['goal_terms'] ?? null),
            scenes: $scenes,
        );
    }

    /**
     * Every ability of the whole plan, in P1's own order: scene 1's abilities, then scene 2's, and
     * so on. The order is load-bearing — the prompt writes scenes by likelihood, so when a plan does
     * not fit, the scheduler drops from the END.
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
     * Assembled here and never asked of the model. v0 asked, and the answer drifted from the days it
     * was supposed to be a copy of — promising the learner an exam harder than the plan they took.
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

    /**
     * The scene's opening lines, from either shape.
     *
     * v0.4 answers with plain utterances; v0.2 wrapped them in a `role` object with a translation
     * beside each. Both are read, because a plan started last week is still running.
     *
     * @param  array<mixed>  $rawScene
     * @return list<string>
     */
    private static function openingLines(array $rawScene): array
    {
        $direct = self::stringList($rawScene['opening_lines'] ?? null);
        if ($direct !== []) {
            return $direct;
        }

        $role = $rawScene['role'] ?? null;
        $lines = is_array($role) ? ($role['opening_lines'] ?? null) : null;
        if (! is_array($lines)) {
            return [];
        }

        $out = [];
        foreach ($lines as $line) {
            $text = is_array($line) ? self::text($line['text'] ?? '') : self::text($line);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }

    /**
     * A v0.2 skeleton's plan-level entities, as names.
     *
     * @return list<string>
     */
    private static function legacyEntityNames(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $entity) {
            $name = is_array($entity) ? self::text($entity['name'] ?? '') : self::text($entity);
            if ($name !== '') {
                $out[] = $name;
            }
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
