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
     */
    public function __construct(
        public array $available,
        public array $global,
        public ?array $override = null,
        public array $effective = [],
    ) {}
}
