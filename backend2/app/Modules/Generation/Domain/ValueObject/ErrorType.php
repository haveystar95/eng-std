<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * The error a distractor sentence deliberately contains. Closed set, and deliberately the classic
 * RU-speaker mistakes rather than "any wrong sentence": a distractor that is wrong in a way a
 * Russian speaker would never produce teaches nothing — it is just noise the learner eliminates
 * by feel. Mirrors the CHECK on `example_distractors.error_type`.
 */
enum ErrorType: string
{
    /** "I went to hospital" for "the hospital" — Russian has no articles. */
    case Article = 'article';

    /** "depends from" for "depends on" — the Russian preposition mapped literally. */
    case Preposition = 'preposition';

    /** "I am working here since 2020" for "I have been working" — no perfect in Russian. */
    case Tense = 'tense';

    /** "I know not this word" — Russian's freer word order carried into English. */
    case WordOrder = 'word_order';

    /** "I want to become a sportsman" for "athlete"; "actual" for "current" — ложный друг. */
    case FalseFriend = 'false_friend';

    /** "I can to swim" — the Russian infinitive after a modal. */
    case ModalTo = 'modal_to';

    public static function tryFromWire(string $value): ?self
    {
        return self::tryFrom(strtolower(trim($value)));
    }
}
