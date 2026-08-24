<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Give me this learner's «Сохранённые» folder, creating it if this is the first save.
 *
 * Lazy on purpose: an empty folder on the shelf of someone who has never used search is clutter,
 * and the only thing that needs the folder to exist is the moment a word is put in it.
 */
final readonly class EnsureDefaultCollection
{
    /**
     * The pair is OPTIONAL and defaults to the owner's profile default, not to `ru→en`: the folder
     * is created the first time a word lands in it, and a learner studying Polish must not be
     * handed an English folder to put a Polish word into (DECISIONS пп. 81, 142).
     *
     * The caller passes a pair only when it already knows one — the save from search, which has the
     * pill the learner picked.
     */
    public function __construct(
        public UserId $ownerId,
        public ?LanguageCode $sourceLang = null,
        public ?LanguageCode $targetLang = null,
    ) {}
}
