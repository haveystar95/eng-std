<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

/** Records every example replacement instead of writing one. */
final class RecordingTermExampleWriter implements TermExampleWriter
{
    /** @var list<array{termId: string, sentence: string, translation: string|null, dropped: list<string>, source: string, promptVersion: string|null}> */
    public array $replaced = [];

    public function replace(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        array $dropDistractorSentences = [],
        string $source = 'user',
        ?Provenance $provenance = null,
    ): void {
        $this->replaced[] = [
            'termId' => $termId->value,
            'sentence' => $sentence,
            'translation' => $sentenceTranslation,
            'dropped' => $dropDistractorSentences,
            'source' => $source,
            'promptVersion' => $provenance?->promptVersion,
        ];
    }
}
