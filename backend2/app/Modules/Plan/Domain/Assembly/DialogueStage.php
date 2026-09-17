<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * «Диалог» (наряд SESSION-1a, разд. 1–2; SPEC §4, D-17): the visit, exchange by exchange, in its order.
 *
 * An `answer` deals `dialogue_partner` then `dialogue_answer` — understand, then reply; an `ask` deals ONE card,
 * `dialogue_ask`, which carries the exchange's check itself (наряд BACK-TAILS-1 §1.5, кадр 33-5: the learner asks, the
 * partner's answer sounds with its text closed, the question on it is asked there, and the text opens after) — the
 * separate check card an ask used to deal is gone; a `rescue` deals one `dialogue_rescue`. An answer
 * followed at once by a rescue is the canvas' 33-6: the learner hears the partner, does not catch it, asks again,
 * and only then replies — `partner(k)`, `rescue(k+1)`, `answer(k)`, and the rescue is not dealt a second time.
 *
 * An exchange with fewer than its two messages (a partner and a learner) is only a bubble of the feed and deals
 * nothing; an ANSWER whose learner line stands on no frame of the scene deals only its `dialogue_partner`, and an ASK
 * whose learner line does the same deals nothing at all — its check lived on the card the frame carries.
 */
final class DialogueStage
{
    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        $exchanges = $scene->lesson->exchanges;
        $drafts = [];
        $dealtRescue = [];
        foreach ($exchanges as $i => $exchange) {
            if (isset($dealtRescue[$i]) || ! self::complete($exchange)) {
                continue;
            }
            switch ($exchange->kind) {
                case ExchangeKind::Answer:
                    $drafts[] = DialogueCards::partner($scene, $exchange);
                    $next = $exchanges[$i + 1] ?? null;
                    if ($next !== null && $next->kind === ExchangeKind::Rescue && self::complete($next)) {
                        $drafts[] = DialogueCards::rescue($scene, $next, $exchange);
                        $dealtRescue[$i + 1] = true;
                    }
                    $drafts[] = DialogueCards::answer($scene, $exchange);
                    break;
                case ExchangeKind::Ask:
                    $drafts[] = DialogueCards::ask($scene, $exchange);
                    break;
                case ExchangeKind::Rescue:
                    $drafts[] = DialogueCards::rescue($scene, $exchange, $exchanges[$i - 1] ?? null);
                    break;
            }
        }

        return array_values(array_filter($drafts, static fn (?CardDraft $d): bool => $d !== null));
    }

    /** Both messages there: at least two, one of them the partner's and one the learner's. */
    private static function complete(Exchange $exchange): bool
    {
        return count($exchange->messages) >= 2 && $exchange->partner() !== null && $exchange->learner() !== null;
    }
}
