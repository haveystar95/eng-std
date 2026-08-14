<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The «Тренажёры» screen's payload. `override` null = this user inherits the global default, which
 * the screen shows as an Inherit/Custom switch rather than as a set that merely looks the same.
 */
final readonly class AdminExerciseModes
{
    /**
     * @param  list<string>       $available
     * @param  list<string>       $global
     * @param  list<string>|null  $override
     * @param  list<string>       $effective
     * @param  list<string>       $noGrade  modes that produce no grade (`intro`) — the panel shows
     *                                      them as a toggle, not as a scored trainer
     */
    public function __construct(
        public array $available,
        public array $global,
        public ?array $override = null,
        public array $effective = [],
        public array $noGrade = [],
        /**
         * @var list<array{mode: string, min_acquisition: string, min_learning_step: int|null, min_reps: int|null, options_policy: string, min_step: int}>
         *      the acquisition-ladder matrix in this scope — which rung opens each trainer
         */
        public array $admission = [],
    ) {}
}
