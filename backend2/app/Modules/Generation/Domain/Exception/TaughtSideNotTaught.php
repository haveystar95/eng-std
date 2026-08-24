<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Generation\Domain\ValueObject\TaughtSide;
use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The client named which side of the pair is the taught one, and named a language this deployment
 * does not teach.
 *
 * A 422 rather than a quiet fall-back to the tie-break, for the same reason
 * {@see UnsupportedLanguagePair} is one: the request states a fact, and a server that answered in
 * the OTHER role would return a term list in the language the learner thinks they are reading. That
 * error looks exactly like a working screen. Since the pills are built from
 * `GET /search/languages`, an app in step cannot produce it — so it is a stale client or a
 * hand-made request, and both want telling.
 */
final class TaughtSideNotTaught extends DomainException implements ProblemDetails
{
    private function __construct(
        string $message,
        private readonly string $side,
        private readonly string $lang,
    ) {
        parent::__construct($message);
    }

    public static function of(TaughtSide $side, string $lang): self
    {
        return new self(
            "«{$lang}» is not a language this deployment teaches, so it cannot be the taught side.",
            $side->value,
            $lang,
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'taught_side_not_taught';
    }

    public function problemTitle(): string
    {
        return 'The named taught side is not a taught language';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['taught_side' => $this->side, 'lang' => $this->lang];
    }
}
