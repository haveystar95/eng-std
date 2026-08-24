<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use DateTimeImmutable;

/**
 * HOW LONG «это не слово» is allowed to stand.
 *
 * A card is a permanent fact and is cached as one. A REFUSAL is not the same kind of fact, and
 * treating it as one cost a real word: on 24.08 a lookup of «привет» in the pair `es ← ru` came
 * back `not_recognized` — the model declined to name the Spanish for a Russian word it handles
 * perfectly well the rest of the time («как дела ?» in the same pair, the same evening, answered
 * `cómo estás`). The row was written, and because the cache is GLOBAL and had no expiry, that one
 * bad second closed the word for every learner in the deployment, forever, at zero cost to the
 * mistake: re-asking was free and re-served the same «no».
 *
 * Twenty-four hours is the shape of the thing being guarded against. The row still does its two
 * jobs — it keeps a paste-and-retry loop from buying the same refusal ten times, and it keeps the
 * daily cap honest by existing — but it stops being a verdict on the word and becomes what it
 * actually is: what the model said last time we asked. `asdfgh` will be refused again tomorrow at
 * the cost of one cheap call; «привет» gets its second chance.
 *
 * There is no button behind this. A «повторить» control would put the learner in charge of spending
 * money on a retry, which is the wrong person to ask — the app simply asks again once the answer is
 * a day old (решение владельца, 24.08).
 */
final class NegativeVerdictLifetime
{
    public const HOURS = 24;

    /** Is a refusal written at [$writtenAt] too old to be served at [$now]? */
    public static function isStale(DateTimeImmutable $writtenAt, DateTimeImmutable $now): bool
    {
        return $writtenAt->modify('+' . self::HOURS . ' hours') <= $now;
    }
}
