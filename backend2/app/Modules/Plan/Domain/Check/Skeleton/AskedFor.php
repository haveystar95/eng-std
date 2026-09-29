<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;

/**
 * WHAT A QUESTION OF THE LEARNER'S ASKS FOR (наряд GEN-4c) — what an `ask` frame is answered with, read off the target's pack:
 *
 *  - a FACT, when it holds a word or a phrase of `question_words` anywhere («Care este programul?», «Je commence à quelle
 *    heure ?»): the reply is the fact itself, no yes and no no;
 *  - a CHOICE, when it offers alternatives — a word of `alternative_words` between two of its words («Is ___ gross or
 *    net?»): answered with one of them, a yes or neither, so no reply of it is a finding (the gate run of GEN-4 asked it six
 *    times, every reply «The amount is gross…»);
 *  - else YES OR NO («Postul include ___?», «Czy potrzebuję ___?»): the reply opens with a word of `yes_no`.
 *
 * A heuristic, as every list of a pack. Where a word of the list stands in a clause and asks nothing («Posso lavorare come
 * ___?», «co miesiąc») the question reads as asking for a fact — the side on which no reply is told to open with a yes.
 */
final readonly class AskedFor
{
    public const YES_NO = 'yes_no';

    public const FACT = 'fact';

    public const CHOICE = 'choice';

    /** @param  self::YES_NO|self::FACT|self::CHOICE  $kind  and the word that decided it — none for a yes or no */
    private function __construct(
        public string $kind,
        public ?string $word,
    ) {}

    public static function of(string $question, LanguageWords $words): self
    {
        $asks = $words->questionWord($question);
        if ($asks !== null) {
            return new self(self::FACT, $asks);
        }
        $or = $words->alternative($question);

        return $or !== null ? new self(self::CHOICE, $or) : new self(self::YES_NO, null);
    }

    /**
     * The keys of the target's pack the reading needs — a rule built on it does not run for a target whose pack lacks one.
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return ['yes_no', 'question_words', 'alternative_words'];
    }
}
