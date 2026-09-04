<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * ONE SCENE'S CONVERSATION, ready for the dialogue screen to play.
 *
 * The unit of the trainer is the EXCHANGE (`docs/plan-dialogue.md` §1), so what goes on the wire is
 * a conversation and not a bag of cards: whose turn each line is, what it says, and which card it is
 * — in the order it is spoken.
 *
 * ## Why the text rides along, when the client has a local mirror
 *
 * Everywhere else on this client a screen reads the local database and the network only moves the
 * cursor. A session payload is the standing exception — it is a self-contained package, so that a
 * sitting can be played through a tunnel that dies halfway — and the conversation is part of the
 * same package for the same reason. The alternative is a dialogue screen that opens on a scene
 * whose delta has not landed yet and draws a conversation of empty bubbles.
 *
 * ## And why a turn is not a task
 *
 * A chain is the WHOLE scene. Some of its turns are cards the sitting owes today and some are cards
 * that closed their rung last week; the screen plays both and hands the learner a move only on the
 * first. Matching is by `term_id` against the sitting's tasks, which is the one name a card keeps.
 */
final readonly class PlanDialogueView
{
    /**
     * @param  list<PlanDialogueTurnView>  $turns  the conversation, in the order it is spoken
     */
    public function __construct(
        /** Which day of the plan this scene is — what the seam's «из прошлых дней» points at. */
        public int $dayIndex,
        /** The scene's name — «Рассказ о прошлом опыте». */
        public ?string $sceneTitle,
        /** The вводка, support language — what the dialogue's own opening screen prints (DL·01). */
        public ?string $sceneIntro,
        public array $turns,
        /**
         * СЦЕНА ДОЗРЕЛА ДО ПРОГОНА — каждый её ход прошёл ступень B хотя бы одним верным выбором
         * ({@see \App\Modules\Learning\Domain\Service\PlanSceneRunGate}).
         *
         * В обычный день это всегда true у сцены, чей прогон вообще собрали: недозревшая секции не
         * получает. Значение появляется на ПОСЛЕДНЕМ дне, где прогоняются все сцены подряд, включая
         * те, до которых лестница не дошла (наряд SCENE-RUN, Ч.2.8): завтра стойка, и сцена, которую
         * человек не успел, — ровно то место, где будет страшно. Итог прогона ставит на такую сцену
         * пометку, а не делает вид, что она была как остальные.
         */
        public bool $runReady = true,
    ) {}
}
