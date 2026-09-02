<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ONE SITUATION WITH ONE INTERLOCUTOR — «регистратура», «кабинет врача», «HR-звонок».
 *
 * What P1 answers in since v0.2, and since v0.4 what a DAY IS. That is the whole difference between
 * this version of the type and the last one: a scene used to be packed into days by capacity — a
 * long one split across two, two short ones merged into one — and the day the learner opened was a
 * slice of a situation rather than a situation. «День = одна сцена (полный тариф — до двух)»
 * (канон §2), so the scene is now the unit the scheduler lays on the calendar, whole.
 *
 * ## The вводка is the part that makes it a scene rather than a topic
 *
 * {@see $intro} — two or three sentences in the learner's own language: who is in front of you,
 * what is about to happen, what counts as success. It is written once, by P1, shown in the plan
 * preview and above the day, and the day's own cards must not retell it
 * ({@see \App\Modules\Generation\Domain\Service\PlanDayValidator::INTRO_REPEATED}). Without it the
 * screen is a list of sentences and the learner has to guess what they are for.
 *
 * ## `role` is derived now, and kept because two screens read it
 *
 * P1 v0.4 answers with `opening_lines` on the scene — plain utterances — and no role OBJECT: there
 * is no name, no `if_silent`. The rehearsal screen and the day's role brief still want «what does
 * the other person say», so it is built from those lines here rather than asked for again. A scene
 * with no lines has no role, exactly as before.
 */
final readonly class PlanScene
{
    /**
     * @param  list<PlanSkill>  $skills        in the order they happen — most likely first
     * @param  list<string>  $openingLines     what the other person actually says in this scene, in
     *                                         the language being learned. Raw material for the
     *                                         «Тебе скажут» shelf: v0.4 asks the day to ADAPT them
     *                                         rather than quote them, so nothing is compared
     *                                         against this list any more.
     * @param  list<string>  $entities         proper names of the scenario — «Zoom», «доктор
     *                                         Ионеску». Filler inside a line, never a card of their
     *                                         own (канон §7).
     */
    public function __construct(
        public int $index,
        public string $title,
        /** 2–3 sentences in the support language. Empty only on a skeleton written before v0.4. */
        public string $intro,
        public array $skills,
        public array $openingLines = [],
        public array $entities = [],
    ) {}

    /**
     * THE PERSON ON THE OTHER SIDE, as the day brief and the rehearsal screen read them.
     *
     * Derived from {@see $openingLines} rather than stored: v0.4's skeleton has no role object, and
     * a name invented here would be a fact about a person nobody described. Null when the scene has
     * nobody to talk to — reading forms alone is a legitimate scene, and the prompt says inventing
     * «сотрудник, который просто рядом» is worse than admitting it.
     */
    public function role(): ?PlanRole
    {
        if ($this->openingLines === []) {
            return null;
        }

        return new PlanRole(
            name: '',
            openingLines: array_map(
                // The translation half is what P1 v0.2 answered with and v0.4 does not: the lines
                // reach the learner as CARDS of the «Тебе скажут» shelf, each with a translation the
                // day wrote. Empty here is the honest value, and every reader already copes.
                static fn (string $line): array => ['text' => $line, 'translation' => ''],
                $this->openingLines,
            ),
            ifSilent: '',
        );
    }

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
