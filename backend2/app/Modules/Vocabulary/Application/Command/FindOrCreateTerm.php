<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\Example;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;

final readonly class FindOrCreateTerm
{
    /**
     * @param list<Translation> $translations
     * @param list<Example> $examples
     */
    public function __construct(
        public LanguageCode $lang,
        public TermText $text,
        public TermType $type,
        public ?PartOfSpeech $pos,
        public TermSource $source,
        public array $translations = [],
        public ?string $ipa = null,
        public array $examples = [],
    ) {}
}
