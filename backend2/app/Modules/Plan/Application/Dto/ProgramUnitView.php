<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * One unit of the day's program — a word, a phrase or an exchange — with where it stands: what the
 * tab's plate counts («8 новых слов», «вернутся в день N»). Its words are the day window's.
 */
final readonly class ProgramUnitView
{
    public const PENDING = 'pending';

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public function __construct(
        public string $unitKind,
        public string $source,
        public string $state,
    ) {}
}
