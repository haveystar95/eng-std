<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * The trainer toggles as the admin panel sees them — plain strings, because the panel must not
 * reach into Learning's Domain enums (deptrac: Admin may use another module's Application only).
 *
 * `override` is null when the user inherits, which is a different state from "an override that
 * happens to equal the default" and is what the panel's Inherit/Custom switch is bound to.
 */
final readonly class ExerciseModeSettingsView
{
    /**
     * @param  list<string>       $available  every mode this build can deal, in enum order
     * @param  list<string>       $global     the product default, in rotation order
     * @param  list<string>|null  $override   this user's own set, or null when they inherit
     * @param  list<string>       $effective  what the user actually trains with
     */
    public function __construct(
        public array $available,
        public array $global,
        public ?array $override = null,
        public array $effective = [],
        /**
         * @var list<string> the subset of `available` that produces NO grade — today just `intro`.
         *
         * A flag rather than an omission: `intro` is a trainer with a toggle like any other, and
         * hiding it from the registry would mean the one card the ladder starts with could not be
         * switched on. The panel needs to know it grades nothing so it does not offer it settings
         * (a difficulty, a share of the session) that mean nothing for a card that asks nothing.
         */
        public array $noGrade = [],
        /**
         * @var list<array{mode: string, min_acquisition: string, min_learning_step: int|null, min_reps: int|null, options_policy: string, min_step: int}>
         *
         * The ADMISSION MATRIX as it applies here: the product default when no user is named, the
         * user's effective matrix otherwise. Read-only from the panel's point of view until its
         * screen lands; the write goes through SetModeAdmission, one mode at a time, because
         * on/off and where-on-the-ladder are independent settings.
         */
        public array $admission = [],
    ) {}
}
