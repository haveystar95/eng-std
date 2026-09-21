<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * «ФРАЗЫ» (наряд SESSION-1a; SESSION-1d — фраза через разные окна; наряд FIX-2 и его доработка): every frame of the
 * day is met, recognised through several windows and said; after all of them ONE `phrase_combine`.
 *
 * - a frame's cards are its intro, its recognitions and its production, in that order ({@see PhraseSeries} decides
 *   which filler and which kind each of them is);
 * - RECOGNITIONS: every frame with a window gets as many as it has fillers, up to two; a frame without a window gets
 *   one. A THIRD goes to the frames of three fillers and more whose values the dialogue says most (more lines on the
 *   frame; between two as many — the one said earlier in the visit), one after another while the stage still fits in
 *   its ceiling with it (решение архитектора 16.09: two recognitions for every frame, no ceiling in cards);
 * - PRODUCTION: ONE trainer at every level (наряд FIX-2, п. 5) — «Скажи целиком» (`phrase_other_slot`) for every
 *   frame WITH a window, in rounds over its values and ending with the learner's own; a frame without a window has
 *   no value to put anywhere and is repeated (`phrase_repeat`). A card that cannot be built is `phrase_repeat` of
 *   the phrase itself. The levels differ in the number of rounds and in nothing else, which is what the live pass
 *   of 20.09 asked for: a beginner's «Фразы» held no «со своим словом» at all;
 * - SPACING ({@see Spacing::apart()}): between two cards of one frame stand at least two cards of other frames, the
 *   intros open their waves, `phrase_combine` is last.
 *
 * ## THE CEILING AND THE LADDER (решение архитектора 20.09, доработка наряда FIX-2; нижняя граница — наряд CONV-2, п. 5)
 *
 * The stage may take {@see BUDGET} seconds by {@see DayPace} — a knob in `config/plan.php`, tuned after the phone.
 * Over it, the stage is cut in ONE order and no other, a rung at a time, stopping the moment it fits:
 *
 *   1. the THIRD recognition — it is simply not added, which is the rule that was already there;
 *   2. the THIRD value round of «Скажи целиком» — a frame of three values or more keeps two;
 *   3. nothing more — the stage is dealt over its ceiling and the excess is the SIGNAL.
 *
 * Rung 2 takes frames in {@see cutOrder()}: the ones the dialogue says LEAST first (fewer lines on the frame; between
 * two as few — the one said LATER in the visit), which is the mirror of the order the thirds are given in. What is cut
 * first is what the day leans on least.
 *
 * THE FLOOR is «два узнавания + ДВА круга + своё» for a frame with two values or more (one round where it has one):
 * two value rounds are not a rung of the ladder but its bottom. Until наряд CONV-2 a third rung took the SECOND round
 * away, and on the owner's gym day (21.09, intermediate) six frames of seven were said with one value only — «That works
 * for me on ___» came to «weekdays» and never to «weekends»: a window said with one value is a sentence learned by
 * heart, not a window. The own-word round is never cut and the trainer is never removed either. If the stage is still
 * over the ceiling with the ladder spent, the day is dealt anyway and the excess is the signal it was meant to be: a
 * stage that refused to be dealt would be a worse answer than a long one.
 */
final class PhrasesStage
{
    /** How long «Фразы» may take by the day's pace before the ladder starts cutting (`plan.phrases_budget`). */
    public const BUDGET = 690;

    /** The recognitions every frame with a window gets — as many as its fillers, up to this. */
    public const RECOGNITIONS = 2;

    /** What rung 2 of the ladder caps the value rounds at — and the floor: two rounds are never cut (наряд CONV-2, п. 5). */
    private const ROUNDS_FLOOR = 2;

    private readonly PhraseSeries $series;

    public function __construct(
        private readonly PhraseCards $cards = new PhraseCards,
        private readonly DayPace $pace = new DayPace,
        private readonly int $budget = self::BUDGET,
    ) {
        $this->series = new PhraseSeries($this->cards);
    }

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene, PlanLevel $level): array
    {
        $phrases = $scene->phrases();
        $recognitions = [];
        $produce = [];
        $mostRounds = [];
        foreach ($phrases as $phrase) {
            $ref = $phrase->ref();
            $recognitions[$ref] = [];
            for ($i = 0; $i < min(self::RECOGNITIONS, PhraseSeries::most($scene, $phrase)); $i++) {
                $card = $this->series->recognition($scene, $phrase, $i);
                if ($card !== null) {
                    $recognitions[$ref][] = $card;
                }
            }
            $produce[$ref] = PhraseCards::hasSlot($phrase) ? CardKind::PhraseOtherSlot : CardKind::PhraseRepeat;
            $mostRounds[$ref] = null;
        }
        $combine = $this->cards->combine($scene);

        /** The production of every frame as the cuts made so far leave it. */
        $productions = function () use ($scene, $phrases, $produce, $level, &$mostRounds): array {
            $out = [];
            foreach ($phrases as $phrase) {
                $ref = $phrase->ref();
                $out[$ref] = $this->production($scene, $phrase, $produce[$ref], $level, $mostRounds[$ref]);
            }

            return $out;
        };
        /** What the stage costs with those productions and the recognitions as they stand. */
        $cost = function (array $made) use ($phrases, $combine, &$recognitions): int {
            $seconds = $combine === null ? 0 : $this->pace->seconds($combine->kind, $combine->payload);
            foreach ($phrases as $phrase) {
                $ref = $phrase->ref();
                $seconds += $this->pace->seconds(CardKind::PhraseIntro)
                    + $this->pace->seconds($made[$ref]->kind, $made[$ref]->payload)
                    + array_sum(array_map(fn (CardDraft $d): int => $this->pace->seconds($d->kind, $d->payload), $recognitions[$ref]));
            }

            return $seconds;
        };

        $made = $productions();
        $seconds = $cost($made);

        // Rung 1 — a third recognition to the most said frames while the stage still fits with it.
        foreach ($this->mostSaid($scene) as $phrase) {
            if (count($recognitions[$phrase->ref()]) !== self::RECOGNITIONS) {
                continue;
            }
            $third = $this->series->recognition($scene, $phrase, self::RECOGNITIONS);
            if ($third === null) {
                continue;
            }
            if ($seconds + $this->pace->seconds($third->kind, $third->payload) > $this->budget) {
                break;
            }
            $recognitions[$phrase->ref()][] = $third;
            $seconds += $this->pace->seconds($third->kind, $third->payload);
        }

        // Rung 2 — the third value round, off the least said frames first. Two rounds are the floor and the own word is
        // not on the ladder: past this rung the stage goes over its ceiling rather than lose either.
        foreach ($this->cutOrder($scene) as $phrase) {
            if ($seconds <= $this->budget) {
                break;
            }
            $ref = $phrase->ref();
            if ($produce[$ref] !== CardKind::PhraseOtherSlot) {
                continue;
            }
            if (count(PhraseCards::rounds($scene, $phrase, $level)) <= self::ROUNDS_FLOOR) {
                continue;
            }
            $mostRounds[$ref] = self::ROUNDS_FLOOR;
            $made = $productions();
            $seconds = $cost($made);
        }

        $units = [];
        foreach ($phrases as $phrase) {
            $ref = $phrase->ref();
            $units[] = [$this->cards->intro($scene, $phrase), ...$recognitions[$ref], $made[$ref]];
        }

        return Spacing::apart($units, $combine);
    }

    /**
     * What a dealt stage costs by the day's pace — what the ceiling is read against, and what a caller checks when it
     * wants to know whether the ladder ran out of rungs.
     *
     * @param  list<CardDraft>  $drafts
     */
    public function seconds(array $drafts): int
    {
        return array_sum(array_map(fn (CardDraft $d): int => $this->pace->seconds($d->kind, $d->payload), $drafts));
    }

    /**
     * The card a frame that failed twice comes back as (SESSION-1d): the kind it failed as the last time, said with
     * another filler — a recognition as that recognition, a phrase said aloud as that production; `phrase_combine` as
     * the frame's own combine. A kind unknown, or a card that cannot be built again — the frame's first recognition.
     * None when its scene has nothing to choose between.
     */
    public function returned(SceneMaterial $scene, PlanTerm $phrase, ?CardKind $failedAs, ?int $failedFiller, PlanLevel $level): ?CardDraft
    {
        $draft = match (true) {
            $failedAs === CardKind::PhraseCombine => $this->cards->combine($scene, $phrase),
            $failedAs !== null => $this->series->again($failedAs, $scene, $phrase, $failedFiller, $failedFiller === null ? [] : [$failedFiller], $level),
            default => null,
        };

        return $draft ?? $this->series->recognition($scene, $phrase, 0);
    }

    /**
     * The copy of a phrase card failed the first time today (SESSION-1d): the same kind said with the next filler no
     * card of the frame has taken today — none free, the next one other than the failed one. Null for a card said with
     * no filler of its own (`phrase_combine`): it comes back as it was.
     *
     * @param  list<int>  $used  the fillers the frame's cards of the day are said with
     */
    public function again(SceneMaterial $scene, PlanTerm $phrase, CardKind $kind, ?int $failed, array $used, PlanLevel $level): ?CardDraft
    {
        return $this->series->again($kind, $scene, $phrase, $failed, $used, $level);
    }

    /**
     * The production of a frame: «Скажи целиком» for a window, else the phrase repeated — and a repeat when it fails.
     *
     * @param  int|null  $mostRounds  the value rounds the ladder left it, null for all of them
     */
    private function production(SceneMaterial $scene, PlanTerm $phrase, CardKind $kind, PlanLevel $level, ?int $mostRounds = null): CardDraft
    {
        $draft = $kind === CardKind::PhraseOtherSlot ? $this->cards->sayWhole($scene, $phrase, $level, $mostRounds) : null;
        if ($draft !== null) {
            return $draft;
        }
        foreach (PhraseSeries::repeatFillers($scene, $phrase) as $filler) {
            $repeat = $this->cards->repeat($scene, $phrase, $filler);
            if ($repeat !== null) {
                return $repeat;
            }
        }

        return $this->cards->saidRepeat($scene, $phrase);
    }

    /**
     * The frames that can take a third recognition — a window and three fillers or more — the most said first: more
     * lines of the dialogue on the frame, then the one said earlier in the visit, then the frame's own order.
     *
     * @return list<PlanTerm>
     */
    private function mostSaid(SceneMaterial $scene): array
    {
        $frames = [];
        foreach ($scene->phrases() as $order => $phrase) {
            if (PhraseSeries::most($scene, $phrase) <= self::RECOGNITIONS) {
                continue;
            }
            $lines = $scene->lesson->linesOf($phrase->ref());
            $frames[] = ['phrase' => $phrase, 'lines' => count($lines), 'first' => $lines[0]['exchange']->step ?? PHP_INT_MAX, 'order' => $order];
        }
        usort($frames, static fn (array $a, array $b): int => [$b['lines'], $a['first'], $a['order']] <=> [$a['lines'], $b['first'], $b['order']]);

        return array_column($frames, 'phrase');
    }

    /**
     * The order rung 2 of the ladder takes frames in — the MIRROR of {@see mostSaid()}: the frames the dialogue
     * says LEAST first (fewer lines on the frame, then the one said LATER in the visit, then the frame's own order
     * reversed). What the day leans on least loses its rounds first.
     *
     * @return list<PlanTerm>
     */
    private function cutOrder(SceneMaterial $scene): array
    {
        $frames = [];
        foreach ($scene->phrases() as $order => $phrase) {
            $lines = $scene->lesson->linesOf($phrase->ref());
            $frames[] = ['phrase' => $phrase, 'lines' => count($lines), 'first' => $lines[0]['exchange']->step ?? PHP_INT_MAX, 'order' => $order];
        }
        usort($frames, static fn (array $a, array $b): int => [$a['lines'], $b['first'], $b['order']] <=> [$b['lines'], $a['first'], $a['order']]);

        return array_column($frames, 'phrase');
    }
}
