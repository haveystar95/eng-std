<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

/**
 * One group the pair broke, said in the words a human proof-reader needs.
 *
 * The group NAME alone («us/me») is enough to count rows and not enough to read them: it says a
 * category is off, not which word of THIS pair made it so. `$words` are the actual words — the
 * source's for a LOST gap, the translation's for an EXTRA one — and `$expected` is the rule's own
 * criterion spelled out: for LOST, the forms that would have cleared it; for EXTRA, the source words
 * that would have licensed what the translation says.
 *
 * Spelling the criterion out is what makes a false positive visible AS a false positive: a reader
 * who sees «свой» is not among the accepted forms knows why a perfectly good translation was
 * flagged. The value object carries no verdict and no suggestion — the rule is coarse on purpose,
 * and this is the evidence it has, not a fix it proposes.
 */
final readonly class AddresseeGap
{
    /**
     * @param  string  $group  the group's name, as the rule declares it
     * @param  list<string>  $words  LOST: the source's own words left unanswered, in source order.
     *                       EXTRA: the translation's words that nothing in the source licenses.
     * @param  list<string>  $expected  LOST: the forms in the translation's language that would have
     *                       cleared the group. EXTRA: the source words that would have licensed it.
     */
    public function __construct(
        public AddresseeDirection $direction,
        public string $group,
        public array $words,
        public array $expected,
    ) {}
}
