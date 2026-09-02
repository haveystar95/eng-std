<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Domain\ValueObject\RescuePhrase;

/**
 * THE LANGUAGE PACK'S RESCUE KIT — five phrases per pair, read rather than generated (канон §5).
 *
 * A port and not a static list, for the reason every other content decision here is one: the kit is
 * a product judgement that will move — a sixth phrase, a better wording — and moving it must not be
 * a deploy of the Domain. The Application layer asks for the pair it is writing a day for and gets
 * whatever the pack holds; an empty answer is a legitimate state («этого языка пакет ещё не
 * написан») and the plan then simply has no kit, rather than a plan that refuses to build.
 */
interface RescueKitSource
{
    /**
     * @return list<RescuePhrase> in the order they are taught — empty when this pair has no pack
     */
    public function forPair(string $targetLang, string $supportLang): array;
}
