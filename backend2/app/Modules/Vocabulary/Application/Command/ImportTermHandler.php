<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;

final readonly class ImportTermHandler
{
    public function __construct(private FindOrCreateTermHandler $findOrCreate) {}

    public function __invoke(ImportTerm $command): TermId
    {
        $translations = [];
        foreach ($command->translations as $translation) {
            $translations[] = new Translation($translation->lang, $translation->text, $translation->isPrimary);
        }

        return ($this->findOrCreate)(new FindOrCreateTerm(
            lang: $command->lang,
            text: new TermText($command->text),
            type: TermType::from($command->type),
            pos: $command->pos !== null ? PartOfSpeech::from($command->pos) : null,
            source: TermSource::from($command->source),
            translations: $translations,
        ));
    }
}
