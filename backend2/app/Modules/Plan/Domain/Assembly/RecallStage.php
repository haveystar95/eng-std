<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «ВСПОМНИТЬ» — the rehearsal's first stage (наряд CONV-1, кадры 37-1, 37-3, 37-4).
 *
 * The rehearsal used to be twelve `speak_answer` over every scene and nothing else; that is gone.
 * What is left before the talk is a REMINDER, not an exam: one walkthrough card that shows every
 * line the learner will need, scene by scene, with its translation and its voice — and then five or
 * six of those lines said aloud on the screen the day already has (`speak_retell`, кадр 35-4). The
 * stage summary is 30-6 with «Вспомнил · около N минут».
 *
 * WHICH LINES ARE SAID ALOUD. «Самые частые в диалоге реплики всех сцен»: the lines are grouped by
 * the FRAME they stand on and the frames are ranked by how many lines of the visit stand on them —
 * a frame the dialogue leans on twice is worth more than one it says once. Ties go to the earlier
 * scene and then the earlier step, so the choice is deterministic: the same rehearsal dealt twice is
 * the same rehearsal. One line per frame (the earliest), at most {@see LINES}.
 */
final class RecallStage
{
    /** How many of the plan's own lines are said aloud before the talk — the order's «5–6». */
    public const LINES = 6;

    /**
     * @param  list<SceneMaterial>  $scenes  every ready scene of the plan, in the plan's order
     * @return list<CardDraft>
     */
    public function build(array $scenes): array
    {
        if ($scenes === []) {
            return [];
        }
        $out = [new CardDraft(
            CardKind::RecallScenes, UnitKind::Day, UnitKind::Day->value, RecallCards::scenes($scenes),
        )];

        foreach (self::lines($scenes) as ['scene' => $scene, 'exchange' => $exchange]) {
            $payload = SpeakCards::retell($scene, $exchange);
            if ($payload !== null) {
                $out[] = new CardDraft(
                    CardKind::SpeakRetell, UnitKind::Exchange, SpokenLines::exchangeRef($exchange->step), $payload,
                );
            }
        }

        return $out;
    }

    /**
     * The lines said aloud, ranked as the class note describes.
     *
     * @param  list<SceneMaterial>  $scenes
     * @return list<array{scene: SceneMaterial, exchange: Exchange}>
     */
    public static function lines(array $scenes): array
    {
        /** @var array<string, array{order: int, step: int, count: int, scene: SceneMaterial, exchange: Exchange}> $frames */
        $frames = [];
        foreach ($scenes as $order => $scene) {
            $seen = [];
            foreach ($scene->lesson->exchanges as $exchange) {
                $learner = $exchange->learner();
                if ($exchange->kind === ExchangeKind::Rescue || $learner === null || $learner->phraseId === null) {
                    continue;
                }
                if (isset($seen[$exchange->step])) {
                    continue;
                }
                $seen[$exchange->step] = true;
                $key = $scene->sceneId->value.':'.$learner->phraseId;
                if (isset($frames[$key])) {
                    $frames[$key]['count']++;

                    continue;
                }
                $frames[$key] = [
                    'order' => $order, 'step' => $exchange->step, 'count' => 1,
                    'scene' => $scene, 'exchange' => $exchange,
                ];
            }
        }

        $ranked = array_values($frames);
        usort($ranked, static fn (array $a, array $b): int => [-$a['count'], $a['order'], $a['step']] <=> [-$b['count'], $b['order'], $b['step']]);

        return array_map(
            static fn (array $row): array => ['scene' => $row['scene'], 'exchange' => $row['exchange']],
            array_slice($ranked, 0, self::LINES),
        );
    }
}
