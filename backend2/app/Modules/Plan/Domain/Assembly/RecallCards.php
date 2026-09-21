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
 * so the sheet says exactly what «Говорю сам» and the day window say — and a line of the sheet is
 * `{ref, text_target, text_native, audio}` and nothing more (наряд BACK-TAILS-2 §5): the learner's own
 * words, in the order of the visit; no line of the partner's rides here in any field. A scene is named by
 * the PLAN (§6), the name the route and the talk give it — never by the lesson's own title for it.
 */
final class RecallCards
{
    /** What one line of the sheet says: the learner's line, its translation and its voice. */
    private const LINE = ['ref', 'text_target', 'text_native', 'audio'];

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
                    $lines[] = array_intersect_key($line, array_flip(self::LINE));
                }
            }
            $out[] = [
                'scene_id' => $scene->sceneId->value,
                'title_target' => $scene->titleTarget,
                'title_native' => $scene->titleNative,
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
