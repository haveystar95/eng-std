<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1–2; SPEC §4; наряд CONV-2, п. 6): the learner's own lines said from their
 * meaning, echoed after a pause and said back — and the speaking of the review day. EVERY card of the stage is a line of
 * the LEARNER's: «Говорю сам» is the learner's part, and the partner's words are not a line anybody asks them to say.
 *
 * A scene day deals `speak_answer` on the complete `answer`/`ask` exchanges whose learner line stands on a frame of
 * the day, in the order of the visit, at most {@see MAX_ANSWERS}; then `speak_echo` — «Повтори через паузу», кадр 35-3 —
 * and last `speak_retell` — «Повтори свою реплику» (наряд BACK-TAILS-1 §1.1, кадр 35-4). Both say back a learner line of
 * at most eighteen words of a complete `answer`/`ask` exchange, and they share out the lines no `speak_answer` took
 * ({@see learnerLines()}): the retell takes the longest, the echo the next one. With none left for it, the echo
 * takes the longest line of the day the retell did not take — the one place the stage asks for a line twice, and it
 * asks it as a repetition, after the learner has already said it from its meaning. With no learner line at all there
 * is no echo (наряд CONV-2: until 21.09 it echoed the longest PARTNER line, and the owner's gym day asked him to say the
 * receptionist's rules).
 *
 * The REVIEW day deals `speak_answer` over the eligible exchanges of its scenes, capped and picked by a seeded
 * shuffle — a day dealt twice is the same day — and served back in the order of the visit. The REHEARSAL deals none
 * of this any more (наряд CONV-1): its twelve `speak_answer` over every scene were replaced by «Вспомнить»
 * ({@see RecallStage}) and the talk with the agent, and the old selection is gone rather than switched off.
 */
final class SpeakStage
{
    public const MAX_ANSWERS = 6;

    /** The longest learner line the echo and the retell say back, in words. */
    public const MAX_WORDS = 18;

    public const REVIEW_MAX = 10;

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        $answers = $this->eligible($scene);
        $answered = array_slice(array_keys($answers), 0, self::MAX_ANSWERS);
        $out = array_slice(array_values($answers), 0, self::MAX_ANSWERS);

        $free = self::learnerLines($scene, $answered);
        $retell = $free[0] ?? null;
        $echo = $free[1] ?? self::learnerLines($scene, $retell === null ? [] : [$retell->step])[0] ?? null;

        $echoPayload = $echo === null ? null : SpeakCards::echoLine($scene, $echo);
        if ($echo !== null && $echoPayload !== null) {
            $out[] = new CardDraft(CardKind::SpeakEcho, UnitKind::Exchange, SpokenLines::exchangeRef($echo->step), $echoPayload);
        }
        $retellPayload = $retell === null ? null : SpeakCards::retell($scene, $retell);
        if ($retell !== null && $retellPayload !== null) {
            $out[] = new CardDraft(CardKind::SpeakRetell, UnitKind::Exchange, SpokenLines::exchangeRef($retell->step), $retellPayload);
        }

        return $out;
    }

    /**
     * The exchanges whose learner line the stage may say back — longest line first (at most
     * {@see MAX_WORDS} words; between two of one length the lower step), among the complete
     * `answer`/`ask` exchanges and passing over the steps given. A rescue line is walked through in «Диалог»: it is a
     * request to repeat, not a line of the learner's own to say again.
     *
     * @param  list<int>  $except  the steps not to take
     * @return list<Exchange>
     */
    private static function learnerLines(SceneMaterial $scene, array $except): array
    {
        $taken = array_fill_keys($except, true);
        $lines = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            if ($exchange->kind === ExchangeKind::Rescue || $learner === null || $exchange->partner() === null
                || isset($taken[$exchange->step]) || isset($lines[$exchange->step])) {
                continue;
            }
            $count = Words::count($learner->textTarget);
            if ($count === 0 || $count > self::MAX_WORDS) {
                continue;
            }
            $lines[$exchange->step] = ['exchange' => $exchange, 'words' => $count];
        }
        uasort($lines, static fn (array $a, array $b): int => [$b['words'], $a['exchange']->step] <=> [$a['words'], $b['exchange']->step]);

        return array_values(array_map(static fn (array $l): Exchange => $l['exchange'], $lines));
    }

    /**
     * The `speak_answer` of one exchange — for the stage, a returned exchange and the review; null when
     * it has none: a rescue, an exchange missing one of its two lines, a learner line on no frame of the day.
     */
    public function speakAnswer(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        if ($exchange->kind === ExchangeKind::Rescue || $exchange->partner() === null) {
            return null;
        }
        $phrase = $scene->phraseTerm($exchange->learner()?->phraseId);
        $payload = $phrase === null ? null : SpeakCards::answer($scene, $exchange, $phrase);

        return $payload === null
            ? null
            : new CardDraft(CardKind::SpeakAnswer, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step), $payload);
    }

    /**
     * The review day's speaking: `speak_answer` over the eligible exchanges of the given scenes, those returned excluded.
     * Shuffled by the seed, the first {@see REVIEW_MAX} kept, and put back in the order of the scenes and their steps.
     *
     * @param  list<SceneMaterial>  $scenes  the two previous scene days' scenes, in order
     * @param  list<string>  $excludedKeys  `sceneId:exchange:xN` of the exchanges already returned
     * @return list<CardDraft>
     */
    public function review(array $scenes, array $excludedKeys, string $seed): array
    {
        $excluded = array_fill_keys($excludedKeys, true);
        $pool = [];
        foreach ($scenes as $order => $scene) {
            foreach ($this->eligible($scene) as $step => $draft) {
                if (isset($excluded[UnitStates::key($scene->sceneId->value, UnitKind::Exchange, $draft->unitRef)])) {
                    continue;
                }
                $pool[] = ['scene' => $order, 'step' => $step, 'draft' => $draft];
            }
        }

        return self::inVisitOrder(array_slice(Shuffle::seeded($seed, $pool), 0, self::REVIEW_MAX));
    }

    /**
     * Every `speak_answer` the scene can deal, by step, in the order of the visit — a step the lesson repeats (the
     * model's slip, counted by the checks) is one exchange, the first.
     *
     * @return array<int, CardDraft>
     */
    private function eligible(SceneMaterial $scene): array
    {
        $out = [];
        $seen = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            if (isset($seen[$exchange->step])) {
                continue;
            }
            $seen[$exchange->step] = true;
            $draft = $this->speakAnswer($scene, $exchange);
            if ($draft !== null) {
                $out[$exchange->step] = $draft;
            }
        }

        return $out;
    }

    /**
     * @param  list<array{scene: int, step: int, draft: CardDraft}>  $picked
     * @return list<CardDraft>
     */
    private static function inVisitOrder(array $picked): array
    {
        usort($picked, static fn (array $a, array $b): int => [$a['scene'], $a['step']] <=> [$b['scene'], $b['step']]);

        return array_map(static fn (array $p): CardDraft => $p['draft'], $picked);
    }
}
