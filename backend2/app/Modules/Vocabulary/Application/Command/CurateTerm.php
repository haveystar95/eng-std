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
    ) {}

    public function touchesExample(): bool
    {
        return $this->exampleId !== null && $this->exampleSentence !== null;
    }
}
