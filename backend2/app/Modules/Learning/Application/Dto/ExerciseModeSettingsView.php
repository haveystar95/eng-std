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
    ) {}
}
