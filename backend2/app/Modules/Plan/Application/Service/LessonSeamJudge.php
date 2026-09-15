<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
use Throwable;

/**
 * THE SEAM JUDGE (`filler.native_seam`, наряд GEN-2b): whether a native frame said with its filler reads as the
 * learner's language is no code's to say — «Можно с ___?» + «собакой» reads, «___ разрешён?» + «собака» does not,
 * and every language breaks differently. So a cheap model reads them: ONE call a day, every sentence the day's
 * frames make listed in it, a yes or a no for each. No rule of any language is written here or in the prompt.
 *
 * A warning, never fatal: a judge that fails or answers off the shape leaves the day as it is — nothing found,
 * `judge.unavailable` counted by the caller.
 */
final readonly class LessonSeamJudge
{
    public function __construct(private PlanModelPort $model) {}

    public function judge(Lesson $answer, string $nativeLanguage): LessonSeamVerdict
    {
        $items = NativeSeams::of($answer);
        if ($items === []) {
            return new LessonSeamVerdict(LessonSeamVerdict::NOTHING, [], 0, 0, '0.000000', 0);
        }

        try {
            $reply = $this->model->judgeNativeSeams(new NativeSeamJudgeRequest($nativeLanguage, $items));
        } catch (Throwable $e) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, '0.000000', 0, mb_substr($e->getMessage(), 0, 300));
        }

        $verdicts = $reply->payload['verdicts'] ?? null;
        if (! is_array($verdicts)) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, $reply->costUsd, $reply->latencyMs, 'no verdicts in the answer');
        }

        $byId = [];
        foreach ($items as $item) {
            $byId[$item['id']] = $item;
        }
        $violations = [];
        $judged = [];
        foreach ($verdicts as $verdict) {
            $id = is_array($verdict) ? ($verdict['id'] ?? null) : null;
            $reads = is_array($verdict) ? ($verdict['reads'] ?? null) : null;
            if (! is_string($id) || ! is_bool($reads) || ! isset($byId[$id]) || isset($judged[$id])) {
                continue;
            }
            $judged[$id] = true;
            if (! $reads) {
                $item = $byId[$id];
                $violations[] = new LessonViolation(
                    LessonCodes::FILLER_NATIVE_SEAM,
                    $id,
                    "«{$item['sentence']}» («{$item['pattern']}» with «{$item['value']}») does not read as {$nativeLanguage}, the seam judge says",
                );
            }
        }

        if ($judged === []) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, $reply->costUsd, $reply->latencyMs, 'no verdict names a sentence that was sent');
        }

        return new LessonSeamVerdict(LessonSeamVerdict::JUDGED, $violations, count($items), count($judged), $reply->costUsd, $reply->latencyMs);
    }
}
