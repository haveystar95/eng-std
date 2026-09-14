<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The partner's lines a scene's voice still owes, in the plan's language: line reference (`x3`) → text. */
final readonly class RoleLines
{
    /** @param  array<string, string>  $lines */
    public function __construct(
        public string $lang,
        public array $lines,
    ) {}
}
