<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Add a word to a collection by its text (the mobile "add word" flow): the term is
 * found-or-created in Vocabulary, then attached. Distinct from AddTermToCollection, which
 * takes an already-known term id.
 *
 * The translation is optional: when omitted, the term is created bare and enrichment is dispatched
 * to fill in translation/transcription/example/photo from the LLM ("add word" promises this).
 */
final readonly class AddWordToCollection
{
    public function __construct(
        public CollectionId $collectionId,
        public UserId $actorId,
        public string $text,
        public ?string $translation = null,
        public string $type = 'word',
    ) {}
}
