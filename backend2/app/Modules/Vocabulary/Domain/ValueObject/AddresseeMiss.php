<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

/**
 * One group the pair tripped, said in the words a human proof-reader needs.
 *
 * The group NAME alone («us/me») is enough to count rows and not enough to read them: it says a
 * category went missing, not which word of THIS term did. «Can you tell me about the growth
 * opportunities?» trips `us/me` because of `me`, and the reader should not have to re-derive that
 * from the term by eye. `$expected` is the rule's own criterion, spelled out: the forms that would
 * have cleared the group. It is there so a false positive is visible AS a false positive — a reader
 * who sees «свій» is not on the list knows why a perfectly good translation was flagged.
 *
 * It carries no verdict and no suggestion. The rule is coarse on purpose; this is the evidence it
 * has, not a fix it proposes.
 */
final readonly class AddresseeMiss
{
    /**
     * @param  string  $group      the group's name, as the rule declares it
     * @param  list<string>  $termWords  the group's triggers this TERM actually uses, in term order
     * @param  list<string>  $expected   the forms in the translation's language that would have cleared it
     */
    public function __construct(
        public string $group,
        public array $termWords,
        public array $expected,
    ) {}
}
