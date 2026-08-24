<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Example;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

final readonly class ReplaceTermExampleHandler
{
    public function __construct(private TermExampleWriter $examples) {}

    public function __invoke(ReplaceTermExample $command): void
    {
        // Through the Example value object rather than straight at the port, so a replaced sentence
        // is canonicalised by the same gate as an imported one. Two ways for content to enter the
        // store is how one of them ends up with a spelling the other cannot match.
        $example = new Example(
            $command->sentence,
            $command->sentenceTranslation,
            new LanguageCode($command->translationLang),
        );

        $this->examples->replace(
            $command->termId,
            $example->sentence,
            $example->sentenceTranslation,
            $command->translationLang,
            $command->dropDistractorSentences,
            $command->source,
            Provenance::forOrNull($command->promptVersion, $command->generationModel),
        );
    }
}
