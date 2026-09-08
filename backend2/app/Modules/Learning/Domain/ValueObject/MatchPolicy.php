<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * HOW an answer is compared against its key — the second half of "what counts as correct", and
 * therefore part of the {@see ExpectedAnswer} rather than a branch inside the grader.
 *
 * It lives on the key and not on the mode because the mode alone cannot answer it: `speaking` asks
 * for the term early and for the example late, and only the two are compared the same way. Whoever
 * builds the key already knows which question was asked, so it is the one place that can say this
 * without re-deriving the rung a second time.
 *
 *   exact     the answer must BE one of the accepted strings once normalised. Every trainer the app
 *             had before speech: a typed word, an assembled sentence, a tapped option.
 *   coverage  the answer must CONTAIN enough of the expected sentence's words. Only reachable when
 *             the answer arrived through a speech recogniser reading a sentence back — see
 *             {@see \App\Modules\Learning\Domain\Service\SpokenCoverage} for why an exact match is
 *             the wrong bar there and what "enough" means. Its own shape now: NO text on screen and
 *             no key — the whole line, asked for entire.
 *   read_aloud    the answer must cover enough of a sentence the learner could SEE while saying it
 *                 — a higher bar than `coverage`, because reading is not recall (наряд SPEECH-2,
 *                 Ч.3.1);
 *   key_and_rest  the key must have been said AND enough of the rest of the reply with it — the
 *                 whole point of Ч.3.2: «сказал проще» stays legal about a PHRASE, not about one
 *                 word.
 */
enum MatchPolicy: string
{
    case Exact = 'exact';
    case Coverage = 'coverage';
    case ReadAloud = 'read_aloud';
    case KeyAndRest = 'key_and_rest';

    /**
     * Судится ли этот ключ речью — то есть {@see \App\Modules\Learning\Domain\Service\SpokenLine},
     * а не тремя ступенями равенства. Один вопрос в одном месте: ветка «а ещё вот эта» в грейдере —
     * это то, как политика равенства однажды достанется транскрипту.
     */
    public function isSpoken(): bool
    {
        return $this !== self::Exact;
    }
}
