<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * WHICH HALF of a search direction is the language being LEARNED — said out loud by the client.
 *
 * A search request carries a DIRECTION («what I typed» → «what I want back») and a direction is not
 * a pair of roles. In `ru → ro` the two coincide, because only one of the two is a language this
 * product teaches. In `de → en` they do not: it is either German studied with English support or
 * English studied with German support, and the query string cannot tell them apart.
 *
 * Until now the server guessed, with the learner's profile as the tie-breaker (DECISIONS п. 147) —
 * a rule that works precisely while a learner studies one language. This is the cure that decision
 * named: the CLIENT knows which pill is the taught one, because the learner set it, so it says so
 * and nothing has to be inferred. The tie-break stays for requests that do not name a side — an
 * older build, a hand-made call, `GET /search` from a screen with no pill at all.
 *
 * The value is a SIDE and not a language code on purpose: `source`/`target` cannot disagree with
 * the pair they refer to, while a third language code in the query string could — and then the
 * server would have to decide which of the two contradicting facts the learner meant.
 */
enum TaughtSide: string
{
    case Source = 'source';
    case Target = 'target';

    /** The language this side names, given the direction it belongs to. */
    public function of(string $source, string $target): string
    {
        return $this === self::Source ? $source : $target;
    }

    /** The other half — the language of support. */
    public function other(string $source, string $target): string
    {
        return $this === self::Source ? $target : $source;
    }

    /** Parse a query-string value; anything else (including an empty string) is «not given». */
    public static function tryFromInput(?string $raw): ?self
    {
        return $raw === null ? null : self::tryFrom(strtolower(trim($raw)));
    }
}
