<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «+5 слов продвинулись · reluctant дошло до „написание"» (кадр 19-2) — THE DAY'S REWARD.
 *
 * The evening screen's one piece of news that is not a counter. «32 из 32» says the work was done;
 * this says what the work BOUGHT, which is the only thing on the screen the learner did not already
 * know when they closed the session.
 *
 * A count AND one example, deliberately: the count alone is another number on a screen full of them,
 * and the example alone hides that four other words moved too. The example is the FURTHEST a word
 * got today — a word reaching «написание» is a better sentence than a word reaching «узнавание»,
 * and the day is allowed to lead with its best moment.
 *
 * The rung is a NUMBER. It is named on the client, in the client's language, from the same
 * `ladderStep*` strings the word card uses — a rung spelled «написание» inside a JSON payload is
 * Russian copy shipped from a server that also answers in English.
 *
 * The whole block is NULL when the day promoted nothing. Not `promoted: 0` — «0 слов продвинулись»
 * is not a reward, and the screen's rule is that a block with nothing to say is not drawn at all.
 */
final readonly class HomeDayAwardView
{
    /**
     * @param  int     $promoted  how many pairs rose at least one rung today
     * @param  string  $termId    the example — the one that got furthest
     * @param  int     $step      the rung that example now stands on (0–5, {@see \App\Modules\Learning\Domain\Service\LearningLadder})
     */
    public function __construct(
        public int $promoted,
        public string $termId,
        public string $text,
        public int $step,
    ) {}
}
