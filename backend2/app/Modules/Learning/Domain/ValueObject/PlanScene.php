<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE SITUATION WITH ONE INTERLOCUTOR — «регистратура», «кабинет врача», «HR-звонок».
 *
 * What P1 answers in since v0.2, and what replaced the outline DAY. The difference is not a
 * rename: a day was a unit of TIME the model was told the size of, and a scene is a unit of
 * MEANING the model finds in the goal. The server packs scenes into days afterwards — a long scene
 * may run across two of them and two short ones may share one — which is only possible because the
 * scene stopped claiming to know how long it takes.
 *
 * `role` is null when the scene genuinely has nobody to talk to (reading forms, labels, signs).
 * The prompt is explicit that inventing an interlocutor is worse than admitting the absence, so
 * every reader here copes with null rather than defaulting it away.
 */
final readonly class PlanScene
{
    /** @param list<PlanSkill> $skills in the order they happen — most likely first */
    public function __construct(
        public int $index,
        public string $title,
        public ?PlanRole $role,
        public array $skills,
    ) {}

    /**
     * What has to be heard in this scene, in skill order.
     *
     * @return list<string>
     */
    public function checkpoints(): array
    {
        return array_map(static fn (PlanSkill $s): string => $s->checkpoint, $this->skills);
    }

    /**
     * Every area this scene's substitution words come from, each once.
     *
     * @return list<string>
     */
    public function topics(): array
    {
        $out = [];
        foreach ($this->skills as $skill) {
            foreach ($skill->topics as $topic) {
                if (! in_array($topic, $out, true)) {
                    $out[] = $topic;
                }
            }
        }

        return $out;
    }
}
