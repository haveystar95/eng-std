<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\UnitStates;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «ГОВОРЮ САМ» (наряд SESSION-1a, разд. 1–2; SPEC §4): the learner's own lines said from their meaning, one partner
 * line echoed, another retold — and the speaking of the review day and of the rehearsal.
 *
 * A scene day deals `speak_answer` on the complete `answer`/`ask` exchanges whose learner line stands on a frame of
 * the day, in the order of the visit, at most {@see MAX_ANSWERS}; then `speak_echo` on the longest partner line of at
 * most eighteen words that `listen_pace` did not take, and `speak_retell` on the next longest — the lines are picked by
 * {@see PartnerLines}, the helper the listening stage picks its pace line with, so the two stages cannot share a line.
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
        $out = array_slice(array_values($this->eligible($scene)), 0, self::MAX_ANSWERS);

        $pace = PartnerLines::pace($scene);
        $taken = $pace === null ? [] : [$pace['step']];
        foreach ([CardKind::SpeakEcho, CardKind::SpeakRetell] as $kind) {
            $line = PartnerLines::longest($scene, PartnerLines::SPEAK_MAX_WORDS, $taken);
            $exchange = $line === null ? null : $scene->exchange($line['step']);
            if ($line === null || $exchange === null) {
                break;
            }
            $taken[] = $line['step'];
            $payload = $kind === CardKind::SpeakEcho
                ? SpeakCards::echoLine($scene, $exchange, $line['message'])
                : SpeakCards::retell($scene, $exchange, $line['message']);
            $out[] = new CardDraft($kind, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step), $payload);
        }

        return $out;
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
     * @return list<CardDraft>
     */
    public function rehearsal(array $scenes): array
    {
        $eligible = array_map(fn (SceneMaterial $scene): array => $this->eligible($scene), $scenes);
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
