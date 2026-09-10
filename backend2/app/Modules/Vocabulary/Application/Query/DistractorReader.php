<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Plausible-but-unambiguously-wrong multiple-choice distractors for a target term: the target
 * TEXT of OTHER terms (never a translation — mixing languages in the options would give the
 * answer away). A candidate is excluded when its translations overlap the target's, so the
 * prompt can't read as correct for both (e.g. "withdraw money" against "withdraw cash"). Prefers
 * the session's own pool (its collection), then tops up from terms of a similar level.
 *
 * THE TOP-UP IS THE CATALOGUE, MINUS WHAT PEOPLE TYPED THEMSELVES. Terms are deduplicated globally,
 * so a row has no owner — but it does record who WROTE it. Generated and curated material is the
 * app's own and is fair game as a wrong answer, whichever shelf it happens to stand on; a term a
 * person entered by hand, in a collection or through the translator (`terms.source = 'user'`), is
 * theirs and never becomes anybody else's option. Nothing but the TEXT ever leaves this reader, so
 * an option says nothing about where it came from or whose it was.
 *
 * EVERY option is in the TARGET'S OWN LANGUAGE, from both sources. That is the pair gate for this
 * kind of card: the options are term texts, so the studied side of the pair is the whole of what
 * shows, and a pool session legitimately mixes pairs — «привет» offered `hello`, `hola` and `ciao`
 * put three correct answers on one card and counted one (owner's device, 26.08).
 */
interface DistractorReader
{
    /**
     * @param  UserId  $userId  the learner the card is for — see the class docblock
     * @param  list<string>  $poolTermIds
     * @return list<string>  up to $count distractor texts
     */
    public function forTarget(UserId $userId, TermId $targetId, array $poolTermIds, int $count): array;
}
