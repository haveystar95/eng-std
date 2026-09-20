<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;

/**
 * «ФРАЗЫ» (наряд SESSION-1a; SESSION-1d — фраза через разные окна): every frame of the day is met, recognised through
 * several windows and said; after all of them ONE `phrase_combine`.
 *
 * - a frame's cards are its intro, its recognitions and its production, in that order ({@see PhraseSeries} decides
 *   which filler and which kind each of them is);
 * - RECOGNITIONS: every frame with a window gets as many as it has fillers, up to two; a frame without a window gets
 *   one. A THIRD goes to the frames of three fillers and more whose values the dialogue says most (more lines on the
 *   frame; between two as many — the one said earlier in the visit), one after another while the stage, by
 *   {@see DayPace}, still fits in {@see THIRD_BUDGET} seconds with it — the first that does not fit ends the thirds
 *   (решение архитектора 16.09: two recognitions for every frame, no ceiling in cards);
 * - PRODUCTION: ONE trainer at every level (наряд FIX-2, п. 5) — «Скажи целиком» (`phrase_other_slot`) for every
 *   frame WITH a window, in rounds over its values and ending with the learner's own; a frame without a window has
 *   no value to put anywhere and is repeated (`phrase_repeat`). A card that cannot be built is `phrase_repeat` of
 *   the phrase itself. The levels differ in the number of rounds and in nothing else, which is what the live pass
 *   of 20.09 asked for: a beginner's «Фразы» held no «со своим словом» at all;
 * - SPACING ({@see Spacing::apart()}): between two cards of one frame stand at least two cards of other frames, the
 *   intros open their waves, `phrase_combine` is last.
 */
final class PhrasesStage
{
    /** How long «Фразы» may take by the day's pace while a third recognition is still added (решение архитектора 16.09). */
    public const THIRD_BUDGET = 540;

    /** The recognitions every frame with a window gets — as many as its fillers, up to this. */
    public const RECOGNITIONS = 2;

    private readonly PhraseSeries $series;

    public function __construct(
        private readonly PhraseCards $cards = new PhraseCards,
        private readonly DayPace $pace = new DayPace,
    ) {
        $this->series = new PhraseSeries($this->cards);
    }

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene, PlanLevel $level): array
    {
        $phrases = $scene->phrases();
        $recognitions = [];
        $produce = [];
        $seconds = 0;
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
            $seconds += $this->pace->seconds(CardKind::PhraseIntro) + $this->pace->seconds($produce[$ref])
                + array_sum(array_map(fn (CardDraft $d): int => $this->pace->seconds($d->kind), $recognitions[$ref]));
        }
        $combine = $this->cards->combine($scene);
        if ($combine !== null) {
            $seconds += $this->pace->seconds($combine->kind);
        }

        foreach ($this->mostSaid($scene) as $phrase) {
            if (count($recognitions[$phrase->ref()]) !== self::RECOGNITIONS) {
                continue;
            }
            $third = $this->series->recognition($scene, $phrase, self::RECOGNITIONS);
            if ($third === null) {
                continue;
            }
            if ($seconds + $this->pace->seconds($third->kind) > self::THIRD_BUDGET) {
                break;
            }
            $recognitions[$phrase->ref()][] = $third;
            $seconds += $this->pace->seconds($third->kind);
        }

        $units = [];
        foreach ($phrases as $phrase) {
            $ref = $phrase->ref();
            $units[] = [$this->cards->intro($scene, $phrase), ...$recognitions[$ref], $this->production($scene, $phrase, $produce[$ref], $level)];
        }

        return Spacing::apart($units, $combine);
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

    /** The production of a frame: «Скажи целиком» for a window, else the phrase repeated — and a repeat when it fails. */
    private function production(SceneMaterial $scene, PlanTerm $phrase, CardKind $kind, PlanLevel $level): CardDraft
    {
        $draft = $kind === CardKind::PhraseOtherSlot ? $this->cards->sayWhole($scene, $phrase, $level) : null;
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

}
