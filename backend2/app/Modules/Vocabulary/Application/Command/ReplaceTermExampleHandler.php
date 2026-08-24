<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

final readonly class ReplaceTermExampleHandler
{
    public function __construct(private TermExampleWriter $examples) {}

    public function __invoke(ReplaceTermExample $command): void
    {
        $this->examples->replace(
            $command->termId,
            $command->sentence,
            $command->sentenceTranslation,
            $command->translationLang,
            $command->dropDistractorSentences,
            $command->source,
            Provenance::forOrNull($command->promptVersion, $command->generationModel),
        );
    }
}
