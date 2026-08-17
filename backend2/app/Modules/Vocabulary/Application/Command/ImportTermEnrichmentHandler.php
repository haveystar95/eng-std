<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Vocabulary\Application\Port\TermEnrichmentWriter;

final readonly class ImportTermEnrichmentHandler
{
    public function __construct(private TermEnrichmentWriter $writer) {}

    public function __invoke(ImportTermEnrichment $command): void
    {
        $this->writer->append(
            $command->termId,
            $command->exampleId,
            $command->variants,
            $command->distractors,
            $command->generatorVersion,
        );
    }
}
