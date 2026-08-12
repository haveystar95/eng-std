<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermCurator;

/**
 * Returns false when there is no such row on that term, or when it is the term's only translation —
 * so a caller can say what did not happen instead of assuming a delete always lands.
 */
final readonly class DropTermTranslationHandler
{
    public function __construct(private TermCurator $curator) {}

    public function __invoke(DropTermTranslation $command): bool
    {
        return $this->curator->dropTranslation($command->termId, $command->translationId);
    }
}
