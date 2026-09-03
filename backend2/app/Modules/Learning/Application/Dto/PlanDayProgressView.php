<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Vocabulary\Application\Dto\TermContentView;

/** One day of a plan with its words' standings worked out. */
final readonly class PlanDayProgressView
{
    /**
     * @param  list<string>  $termIds
     * @param  array<string, PlanTermStanding>  $standings  term id => standing
     * @param  array<string, TermContentView>  $content     term id => content, read through THIS
     *                                                      day's collection so a term shows the
     *                                                      example written for this day's situation
     */
    public function __construct(
        public int $index,
        public ?string $collectionId,
        public array $termIds,
        public array $standings,
        public array $content,
        /**
         * «День пройден» — every one of its words has closed STAGE A.
         *
         * Stage A and not all three, deliberately: a day is a sitting, and what a sitting can
         * honestly promise is that every word of it was met, recognised both ways, assembled and
         * said out loud. Stages B and C need nights, and waiting for them would mean the focus never
         * moves on a three-day plan.
         */
        public bool $passed,
        /**
         * THE SCENE, as much of it as a CARD needs — the вводка's own text, the scene's name, and
         * every ability of the scene by the `skill_ref` its cards point at.
         *
         * Carried here rather than re-read by the session builder because it is the same day object
         * this view was built from, and two reads of one model-written JSON blob is two chances to
         * disagree about what the scene said. Its one reader is the situational card
         * ({@see \App\Modules\Learning\Domain\Service\SituationalPrompt}).
         */
        public ?string $sceneIntro = null,
        public ?string $sceneTitle = null,
        /** @var array<string, string> `skill_ref` => the ability's `outcome`, support language */
        public array $skillOutcomes = [],
    ) {}
}
