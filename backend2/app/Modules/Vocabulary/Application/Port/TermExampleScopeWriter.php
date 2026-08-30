<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * An example that belongs to ONE day.
 *
 * The plan's re-use rule made concrete. A term the learner met on day 1 is not re-taught on day 3;
 * it is re-MET, with sentences written in day 3's situation. Those sentences are true of day 3 and
 * would be noise on the term's general card — «Veterinarul l-a ascultat pe motanul meu înainte de
 * vaccin» is not a good general example of `motanul`, it is a good example of `motanul` at the vet.
 *
 * Idempotent on (term, sentence, scope): a re-run of a day writes the same rows, not more of them.
 */
interface TermExampleScopeWriter
{
    public function write(
        TermId $termId,
        string $sentence,
        ?string $sentenceTranslation,
        string $translationLang,
        CollectionId $scope,
    ): void;
}
