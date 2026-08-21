<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\InstantTranslation;

/**
 * A machine translator, asked for ONE short string.
 *
 * Deliberately the narrowest port in the module, and deliberately NOT an LLM port: no prompt, no
 * schema, no versioning, no retry. It exists to put a grey line under a search field within a
 * blink, and everything a bigger seam would buy costs the only thing this feature has to sell.
 *
 * It is a PORT rather than a DeepL call because the vendor is the replaceable part: the free plan's
 * half-million characters a month is a real ceiling, and the day it is hit the answer must be a
 * different adapter and not a rewrite. Nothing above this interface knows the word «DeepL».
 *
 * ## What this is NOT for
 *
 * A machine translation is a HINT — a first, cheap guess at what a word means, shown while the
 * learner is still typing. It must never become card content: not a term's translation, not an
 * example, not a description. Those are written by the lookup model against a prompt that knows
 * about CEFR level, register and the isomorphism rules, and a translator that knows none of that
 * would quietly poison the catalogue with plausible-looking rows nobody reviewed.
 *
 * {@see isAvailable()} is the honest answer to «is there a key» — an unconfigured deployment is a
 * normal state, not a failure, and the endpoint above says `feature_disabled` rather than erroring.
 */
interface TranslationProvider
{
    /** Can this provider be called at all? False when unconfigured — never an exception. */
    public function isAvailable(): bool;

    /** A short human name for the ledger («deepl»). Stable: it is written into cache rows. */
    public function name(): string;

    /**
     * Translate one short string, or return null when the provider has nothing to say.
     *
     * Null and not an exception for an empty or unusable answer: the caller is a search field, and
     * «no hint» is a perfectly good outcome there. A TRANSPORT failure may throw — the caller
     * catches it and shows nothing, which is the same outcome by a different route.
     */
    public function translate(string $text, string $source, string $target): ?InstantTranslation;
}
