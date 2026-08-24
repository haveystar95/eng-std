<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use App\Modules\Shared\Domain\ValueObject\TermId;
use DomainException;

/**
 * A word of a REFERENCE language offered to the pool.
 *
 * zh and ja are phrasebook languages in v1 (DECISIONS пп. 84, 136): they carry no trainer at all,
 * so there is no card to deal, no answer to grade and no rung to stand on. Enrolling one would
 * write a row the trainer can never serve — a word that is «being studied» and never comes back,
 * which is worse than a refusal because nothing on any screen would say why.
 *
 * A REFUSAL and not a silent no-op, for the same reason {@see
 * \App\Modules\Collections\Domain\Exception\TermLanguageMismatch} is one: the client asked for
 * something and has to be told it did not happen. It is not reachable from the app in normal use —
 * a reference collection shows no «Учить это слово» button at all — so it is a stale client or a
 * hand-made request, and both want telling.
 *
 * «Reference» is DERIVED from the language's capabilities, never stored beside it: see {@see
 * \App\Modules\Shared\Domain\Service\LanguageRoles::isReference()}.
 */
final class ReferenceLanguageTerm extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $termId,
        private readonly string $lang,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(TermId $termId, string $lang): self
    {
        return new self(
            $termId->value,
            $lang,
            "{$lang} — справочный язык: слова на нём читаются и озвучиваются, но не тренируются, "
            . 'поэтому в пул они не зачисляются.',
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'reference_language_term';
    }

    public function problemTitle(): string
    {
        return 'A reference-language term is not studied';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['term_id' => $this->termId, 'lang' => $this->lang];
    }
}
