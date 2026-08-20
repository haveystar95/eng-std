<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermCoreWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;

/** Records every core replacement instead of writing one. */
final class RecordingTermCoreWriter implements TermCoreWriter
{
    /** @var list<array{termId: string, translation: string, lang: string, promptVersion: string, model: string|null, ipa: string|null, cefr: string|null}> */
    public array $replaced = [];

    public function replaceCore(
        TermId $termId,
        string $translation,
        string $translationLang,
        ?string $ipa,
        ?string $cefr,
        ?string $imageApiPrompt,
        Provenance $provenance,
    ): bool {
        $this->replaced[] = [
            'termId' => $termId->value,
            'translation' => $translation,
            'lang' => $translationLang,
            'promptVersion' => $provenance->promptVersion,
            'model' => $provenance->model,
            'ipa' => $ipa,
            'cefr' => $cefr,
        ];

        return true;
    }
}
