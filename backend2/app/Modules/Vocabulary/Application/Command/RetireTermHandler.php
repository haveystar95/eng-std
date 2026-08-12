<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermCurator;

/** Returns false when there is no such live term. */
final readonly class RetireTermHandler
{
    public function __construct(private TermCurator $curator) {}

    public function __invoke(RetireTerm $command): bool
    {
        return $this->curator->retire($command->termId);
    }
}
