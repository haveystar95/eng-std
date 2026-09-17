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
 * «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1–2; SPEC §4): the learner's own lines said from their meaning, one partner
 * line echoed, another retold — and the speaking of the review day and of the rehearsal.
 *
 * A scene day deals `speak_answer` on the complete `answer`/`ask` exchanges whose learner line stands on a frame of
 * the day, in the order of the visit, at most {@see MAX_ANSWERS}; then `speak_echo` on the longest partner line of at
 * most eighteen words that `listen_pace` did not take ({@see PartnerLines}, the helper the listening stage picks its
 * pace line with, so the two stages cannot share a line); and last `speak_retell` — «Повтори свою реплику» (наряд
 * BACK-TAILS-1 §1.1, кадр 35-4) — on a line of the LEARNER'S own: the longest of a complete `answer`/`ask` exchange
 * that no `speak_answer` of this day already took ({@see freeLearnerLine()}). The learner hears themselves, sees only
 * the translation, and says the line back; the client passes it by coverage, without the network.
 *
 * The review and the rehearsal deal `speak_answer` only, over every eligible exchange of their scenes, capped and
 * picked by a seeded shuffle — a day dealt twice is the same day — and served back in the order of the visit.
 */
final class SpeakStage
{
    public const MAX_ANSWERS = 6;

    public const REVIEW_MAX = 10;

    public const REHEARSAL_MAX = 12;

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene): array
    {
        $answers = $this->eligible($scene);
        $out = array_slice(array_values($answers), 0, self::MAX_ANSWERS);

        $pace = PartnerLines::pace($scene);
        $echo = PartnerLines::longest($scene, PartnerLines::SPEAK_MAX_WORDS, $pace === null ? [] : [$pace['step']]);
        $echoExchange = $echo === null ? null : $scene->exchange($echo['step']);
        if ($echo !== null && $echoExchange !== null) {
            $out[] = new CardDraft(
                CardKind::SpeakEcho, UnitKind::Exchange, SpokenLines::exchangeRef($echoExchange->step),
                SpeakCards::echoLine($scene, $echoExchange, $echo['message']),
            );
        }

        $retell = self::freeLearnerLine($scene, array_slice(array_keys($answers), 0, self::MAX_ANSWERS));
        if ($retell !== null) {
            $payload = SpeakCards::retell($scene, $retell, $scene->target);
            if ($payload !== null) {
                $out[] = new CardDraft(CardKind::SpeakRetell, UnitKind::Exchange, SpokenLines::exchangeRef($retell->step), $payload);
            }
        }

        return $out;
    }

    /**
     * The exchange whose learner line `speak_retell` says again (наряд BACK-TAILS-1 §1.1): the longest learner line of
     * at most {@see PartnerLines::SPEAK_MAX_WORDS} words among the complete `answer`/`ask` exchanges — between two of
     * one length the lower step — passing over the exchanges this day already deals a `speak_answer` on, so the stage
     * never asks for the same line twice. Null when every line is taken or none is there: a rescue line is walked
     * through in «Диалог» and is not a line of the learner's own to say again.
     *
     * @param  list<int>  $answered  the steps dealt as `speak_answer` today
     */
    private static function freeLearnerLine(SceneMaterial $scene, array $answered): ?Exchange
    {
        $taken = array_fill_keys($answered, true);
        $best = null;
        $bestCount = 0;
        foreach ($scene->lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            if ($exchange->kind === ExchangeKind::Rescue || $learner === null || $exchange->partner() === null || isset($taken[$exchange->step])) {
                continue;
            }
            $count = Words::count($learner->textTarget);
            if ($count === 0 || $count > PartnerLines::SPEAK_MAX_WORDS) {
                continue;
            }
            if ($best === null || $count > $bestCount) {
                $best = $exchange;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * The `speak_answer` of one exchange — for the stage, a returned exchange, the review and the rehearsal; null when
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
     * The rehearsal: `speak_answer` over every ready scene of the plan, at most {@see REHEARSAL_MAX}. Every scene first
     * gets one card, in the plan's order, while there is room; then, again in order and while there is room, a second
     * card for each scene that has two. Which exchanges a scene gives is a shuffle seeded by the scene, served by step.
     *
     * @param  list<SceneMaterial>  $scenes
     * @param  list<string>  $excludedKeys  exchanges coming back today as returns ({@see UnitStates::key()})
     * @return list<CardDraft>
     */
    public function rehearsal(array $scenes, array $excludedKeys = []): array
    {
        $excluded = array_fill_keys($excludedKeys, true);
        $eligible = array_map(fn (SceneMaterial $scene): array => array_filter(
            $this->eligible($scene),
            static fn (CardDraft $draft): bool => ! isset($excluded[UnitStates::key($scene->sceneId->value, UnitKind::Exchange, $draft->unitRef)]),
        ), $scenes);
        $quota = array_fill(0, count($scenes), 0);
        $total = 0;
        foreach ([1, 2] as $round) {
            foreach ($eligible as $order => $drafts) {
                if ($total >= self::REHEARSAL_MAX) {
                    break 2;
                }
                if (count($drafts) >= $round) {
                    $quota[$order] = $round;
                    $total++;
                }
            }
        }

        $out = [];
        foreach ($scenes as $order => $scene) {
            if ($quota[$order] === 0) {
                continue;
            }
            $pool = [];
            foreach ($eligible[$order] as $step => $draft) {
                $pool[] = ['scene' => $order, 'step' => $step, 'draft' => $draft];
            }
            $out = [...$out, ...self::inVisitOrder(array_slice(Shuffle::seeded('rehearsal:'.$scene->sceneId->value, $pool), 0, $quota[$order]))];
        }

        return $out;
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
