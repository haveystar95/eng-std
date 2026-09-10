<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One unit of the day's program — a word, a phrase or an exchange — with where it stands. */
final readonly class ProgramUnitView
{
    public const PENDING = 'pending';

    public const PASSED = 'passed';

    public const FAILED = 'failed';

    public function __construct(
        public string $unitKind,
        public string $unitRef,
        public string $sceneId,
        public ?string $textTarget,
        public ?string $textNative,
        public string $source,
        public int $cardsTotal,
        public int $cardsDone,
        public string $state,
    ) {}
}
