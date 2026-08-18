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
 *             the wrong bar there and what "enough" means.
 */
enum MatchPolicy: string
{
    case Exact = 'exact';
    case Coverage = 'coverage';
}
