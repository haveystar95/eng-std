<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * THE POSITION A SITUATIONAL CARD PUTS THE LEARNER IN — assembled by the server out of the day, and
 * the one thing that makes stage B a situation rather than a translation.
 *
 * Канон §13: «Подсказка — ситуация, не слово.» So the card must say what is HAPPENING — «Хозяин
 * спросил про залог» — and never what to SAY, because the moment the prompt is the meaning of the
 * right option the card stops asking whether the learner can reach for a reply and starts asking
 * whether they can translate one. That is why the reply's own translation is not an input to this
 * object anywhere: the guarantee is by construction, not by a filter somebody has to remember
 * ({@see \App\Modules\Learning\Domain\Service\SituationalPrompt}).
 *
 * ## Three fields, and the client owns the words around them
 *
 * The server owns the FACTS — this scene, this role line, this ability — because they are facts
 * about the day and only the day holds them. The wording is the client's, in two languages, exactly
 * as it is for `origin` and `speaker` on the task envelope. So there is no assembled sentence here:
 * there are the pieces, and the labels («Ситуация», «Задача», «Сейчас услышите») are drawn on the
 * device.
 */
final readonly class SituationalSituation
{
    /** The paired role line was found: the learner sees what was said to them. */
    public const SOURCE_ROLE_LINE = 'role_line';

    /** No paired role line on this day — the card names the ABILITY it serves instead (канон §8). */
    public const SOURCE_SKILL = 'skill';

    /** «Тебе скажут»: what is about to be heard, named by the scene it happens in. */
    public const SOURCE_SCENE = 'scene';

    public function __construct(
        /** {@see SOURCE_ROLE_LINE}, {@see SOURCE_SKILL} or {@see SOURCE_SCENE}. */
        public string $source,
        /**
         * WHERE THIS IS HAPPENING, on the support language: the first sentence of the scene's вводка
         * for a speak shelf, the scene's own name for a hear one. Null when the day was written
         * before scenes carried either.
         */
        public ?string $context,
        /**
         * WHAT THE LEARNER IS THERE TO DO — the scene skill's `outcome`, verbatim, on the support
         * language. Present only on {@see SOURCE_SKILL}: it is the fallback for a card whose ability
         * no role line of this day shares.
         */
        public ?string $task = null,
        /**
         * WHAT WAS JUST SAID TO THEM, on the language being LEARNED, played aloud — the role line
         * of this day that serves the same ability as this card. Present only on
         * {@see SOURCE_ROLE_LINE}.
         *
         * It is on the card in the studied language on purpose (канон §13: изучаемый язык — только
         * то, что слышишь): the learner hears the question and answers it, which is the whole of the
         * moment being rehearsed.
         */
        public ?string $roleLine = null,
        /** The term that role line IS — so the client can play its audio like any other card. */
        public ?string $roleLineTermId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'context' => $this->context,
            'task' => $this->task,
            'role_line' => $this->roleLine,
            'role_line_term_id' => $this->roleLineTermId,
        ];
    }
}
