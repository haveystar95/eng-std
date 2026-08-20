<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermCoreWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

final readonly class ReplaceTermCoreHandler
{
    public function __construct(private TermCoreWriter $cores) {}

    /** @return bool false when there is no such live term */
    public function __invoke(ReplaceTermCore $command): bool
    {
        return $this->cores->replaceCore(
            $command->termId,
            $command->translation,
            $command->translationLang,
            $command->ipa,
            $command->cefr,
            $command->imageApiPrompt,
            // Required, not optional: a row this path touched can never honestly be un-stamped, so
            // the command demands a version rather than accepting a null and inventing one here.
            new Provenance($command->promptVersion, $command->generationModel),
        );
    }
}
