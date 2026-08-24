<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Make this text the term's primary translation for its language — the question every card of this
 * term asks — demoting whatever held the pin and adding the row if it is not there yet.
 *
 * The deliberate counterpart of {@see ImportTerm}, which by design cannot move a pin that is
 * already set: a generator's opinion never re-words a card somebody is learning from. This command
 * exists for the callers the trust hierarchy puts ABOVE a generator — today exactly one, the
 * learner confirming the translation they were shown in the translator before pressing «Собрать
 * карточку». It is a command and not a flag on the import precisely so that reaching for it is a
 * decision somebody made, visible in a diff.
 */
final readonly class PinTermTranslation
{
    public function __construct(
        public TermId $termId,
        public LanguageCode $lang,
        public string $text,
    ) {}
}
