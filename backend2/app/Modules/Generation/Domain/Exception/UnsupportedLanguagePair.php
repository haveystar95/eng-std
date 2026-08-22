<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The client asked to search in a pair this deployment does not serve.
 *
 * A 422 and not a silent fallback to the learner's profile pair, which is the tempting shortcut:
 * the pill on the search screen SAYS which way the answer will come back, and a server that quietly
 * answered in a different pair would make that label a lie on exactly the screen whose whole job is
 * to be believed. Better a refusal the client can show than an answer in the wrong language.
 *
 * Not reachable from the app in normal use — the pill only offers what `GET /search/languages`
 * returned — so it is a stale client or a hand-made request, both of which want telling.
 */
final class UnsupportedLanguagePair extends DomainException implements ProblemDetails
{
    private function __construct(
        string $message,
        private readonly string $source,
        private readonly string $target,
    ) {
        parent::__construct($message);
    }

    public static function of(string $source, string $target): self
    {
        return new self("«{$source} → {$target}» is not a pair this deployment searches in.", $source, $target);
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'unsupported_language_pair';
    }

    public function problemTitle(): string
    {
        return 'Unsupported language pair';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['source' => $this->source, 'target' => $this->target];
    }
}
