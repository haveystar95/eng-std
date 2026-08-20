<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * REPLACE a term's core — its primary key and the fields around it — with freshly generated content.
 *
 * The one command in this module that overwrites rather than merges; see
 * {@see \App\Modules\Vocabulary\Application\Port\TermCoreWriter} for why the exception exists and
 * what it is confined to. Primitives all the way, like {@see ImportTerm}: the caller lives in another
 * module and must not have to touch Vocabulary's Domain value objects to say where content came from.
 */
final readonly class ReplaceTermCore
{
    /**
     * @param  string|null  $ipa  null leaves the stored value alone — an absent field is the model
     *         saying nothing, never the model saying "empty"
     */
    public function __construct(
        public TermId $termId,
        public string $translation,
        public string $translationLang,
        public string $promptVersion,
        public ?string $generationModel = null,
        public ?string $ipa = null,
        public ?string $cefr = null,
        public ?string $imageApiPrompt = null,
    ) {}
}
