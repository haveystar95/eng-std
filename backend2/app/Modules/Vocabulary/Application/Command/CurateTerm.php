<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * A back-office edit of a term's content. Null fields are left alone.
 *
 * There is no actor id: this is curation of the GLOBAL dictionary, not a user editing their own
 * copy — there are no per-user copies. Authorisation is the admin guard on the route.
 */
final readonly class CurateTerm
{
    public function __construct(
        public TermId $termId,
        public ?string $text = null,
        public ?string $translation = null,
        public ?string $ipa = null,
        public ?string $exampleId = null,
        public ?string $exampleSentence = null,
        public ?string $exampleTranslation = null,
        // Which language `$translation` is written in — the row it replaces AND the label that row
        // carries afterwards. Defaulted to the only learner language the content has today, so the
        // panel's ordinary "fix this translation" stays a one-liner; the retrospective language
        // repair passes it explicitly, because getting it wrong there is what created the mess.
        public string $translationLang = 'ru',
    ) {}

    public function touchesExample(): bool
    {
        return $this->exampleId !== null && $this->exampleSentence !== null;
    }
}
