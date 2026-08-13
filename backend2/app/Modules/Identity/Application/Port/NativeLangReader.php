<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * The user's own language (`profiles.native_language`) — the language a card's QUESTION is written
 * in, and therefore which of a term's translations to show them.
 *
 * The sibling of {@see DefaultTargetLangReader}, which answers the other half (what they are
 * learning). Both exist because a term is global and carries translations in several languages at
 * once: without this, every reader picked whichever row the database happened to return first.
 */
interface NativeLangReader
{
    /**
     * Null when the user has no profile row yet. Callers pass {@see self::FALLBACK} in that case —
     * ru is what every profile in this app has and the column's own default.
     */
    public function nativeLangFor(UserId $userId): ?LanguageCode;

    /** What a caller uses when the user has no profile. Named so the constant is not retyped. */
    public const FALLBACK = 'ru';
}
