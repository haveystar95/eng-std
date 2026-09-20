<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * The payload of «Вспомни свои реплики» (кадр 37-3): the plan's scenes in order, and inside each the
 * learner's OWN lines with their translation and their voice. Nothing is graded here and nothing is
 * chosen — it is the sheet the learner reads before the talk, so the card carries no options, no key
 * and no expected text.
 *
 * Every line is the one the server assembles from its frame and filler ({@see CardObjects::ownLine()}),
 * so the sheet says exactly what «Говорю сам» and the day window say.
 */
final class RecallCards
{
    /**
     * @param  list<SceneMaterial>  $scenes
     * @return array<string, mixed>
     */
    public static function scenes(array $scenes): array
    {
        $out = [];
        foreach ($scenes as $scene) {
            $lines = [];
            $seen = [];
            foreach ($scene->lesson->exchanges as $exchange) {
                if (! self::says($exchange) || isset($seen[$exchange->step])) {
                    continue;
                }
                $seen[$exchange->step] = true;
                $line = CardObjects::ownLine($scene, $exchange);
                if ($line !== null) {
                    $lines[] = ['step' => $exchange->step, ...$line];
                }
            }
            $out[] = [
                'scene_id' => $scene->sceneId->value,
                'title_target' => $scene->lesson->titleTarget,
                'title_native' => $scene->lesson->titleNative,
                'lines' => $lines,
            ];
        }

        $first = $scenes[0] ?? null;

        return [
            'scene_id' => $first?->sceneId->value ?? '',
            'scenes' => $out,
        ];
    }

    /** A line of the learner's own: a complete `answer`/`ask` exchange — a rescue is «попроси повторить», not a line to recall. */
    private static function says(Exchange $exchange): bool
    {
        return $exchange->kind !== ExchangeKind::Rescue && $exchange->learner() !== null && $exchange->partner() !== null;
    }
}
