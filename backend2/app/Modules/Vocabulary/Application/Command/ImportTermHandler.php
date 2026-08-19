<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\Example;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;

final readonly class ImportTermHandler
{
    public function __construct(private FindOrCreateTermHandler $findOrCreate) {}

    public function __invoke(ImportTerm $command): TermId
    {
        // One import is one item out of one model call, so the term, its translations and its
        // example all carry the same stamp. They diverge only later, when a dedup merge adds a
        // line from a NEWER call to a term an older one created — and then each row keeps its own.
        $provenance = Provenance::forOrNull($command->promptVersion, $command->generationModel);

        $translations = [];
        foreach ($command->translations as $translation) {
            $translations[] = new Translation($translation->lang, $translation->text, $translation->isPrimary, $provenance);
        }

        $examples = [];
        foreach ($command->examples as $example) {
            if (trim($example->sentence) === '') {
                continue;
            }
            $examples[] = new Example($example->sentence, $example->sentenceTranslation, $provenance);
        }

        return ($this->findOrCreate)(new FindOrCreateTerm(
            lang: $command->lang,
            text: new TermText($command->text),
            type: TermType::from($command->type),
            pos: $command->pos !== null ? PartOfSpeech::from($command->pos) : null,
            source: TermSource::from($command->source),
            translations: $translations,
            ipa: $command->ipa,
            examples: $examples,
            cefr: $command->cefr,
            imageApiPrompt: $command->imageApiPrompt,
            provenance: $provenance,
        ));
    }
}
