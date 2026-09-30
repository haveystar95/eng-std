<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
use App\Modules\Plan\Domain\Lesson\Phrase;
use Throwable;

/**
 * THE SEAM JUDGE (`filler.native_seam`, наряд GEN-2b): whether a native frame said with its filler reads as the
 * learner's language is no code's to say — «Можно с ___?» + «собакой» reads, «___ разрешён?» + «собака» does not,
 * and every language breaks differently. So a cheap model reads them: every sentence the frames make listed in one call, a
 * yes or a no for each. No rule of any language is written here or in the prompt. Since наряд GEN-4 it reads the SKELETON's
 * frames, before the dialogue — what does not read sends its frame to a repair — and once more the frames a repair changed.
 *
 * AND THE REPLIES THAT NAME A FILLER BY ITS MEANING (`partner.names_filler_meaning`, наряд GEN-4c, `lesson_seam_judge.v1.3`),
 * in the same call: every reply of the partner to a question of the learner's ({@see \App\Modules\Plan\Domain\Lesson\AskReplies})
 * with the values of the question's slot — does the reply name one of them, word for word, in another form or in other words;
 * a yes or a no for each reply, as for each sentence (GEN-4c-2: asked for the list of the replies that name one, the judge
 * listed a reply sent alone whatever it said). `partner.names_filler` finds the value said as it is written; the rest is no
 * code's to say either. A reply that names one sends its partner line to a repair; a line a repair changed is read again with
 * the frames.
 *
 * A warning, never fatal: a judge that fails or answers off the shape leaves the day as it is — nothing found,
 * `judge.unavailable` counted by the caller. A reply the answer gives no verdict on is found naming nothing.
 */
final readonly class LessonSeamJudge
{
    public function __construct(private PlanModelPort $model) {}

    /**
     * @param  list<Phrase>  $phrases  the frames whose native seams to read
     * @param  list<array{id: string, question: string, values: list<string>, reply: string}>  $replies  the replies to read
     */
    public function judge(array $phrases, string $nativeLanguage, array $replies = [], string $targetLanguage = ''): LessonSeamVerdict
    {
        $items = NativeSeams::of($phrases);
        if ($items === [] && $replies === []) {
            return new LessonSeamVerdict(LessonSeamVerdict::NOTHING, [], 0, 0, '0.000000', 0);
        }
        $sent = count($replies);

        try {
            $reply = $this->model->judgeNativeSeams(new NativeSeamJudgeRequest($nativeLanguage, $items, $targetLanguage, $replies));
        } catch (Throwable $e) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, '0.000000', 0, mb_substr($e->getMessage(), 0, 300), $sent);
        }

        $verdicts = $reply->payload['verdicts'] ?? null;
        $answers = $reply->payload['replies'] ?? null;
        if (($items !== [] && ! is_array($verdicts)) || ($items === [] && ! is_array($answers))) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, $reply->costUsd, $reply->latencyMs, 'no verdicts in the answer', $sent);
        }

        $byId = [];
        foreach ($items as $item) {
            $byId[$item['id']] = $item;
        }
        $violations = [];
        $judged = [];
        foreach (is_array($verdicts) ? $verdicts : [] as $verdict) {
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

        if ($items !== [] && $judged === []) {
            return new LessonSeamVerdict(LessonSeamVerdict::UNAVAILABLE, [], count($items), 0, $reply->costUsd, $reply->latencyMs, 'no verdict names a sentence that was sent', $sent);
        }

        $asked = [];
        foreach ($replies as $one) {
            $asked[$one['id']] = $one;
        }
        $read = [];
        $named = [];
        foreach (is_array($answers) ? $answers : [] as $answer) {
            $id = is_array($answer) ? ($answer['id'] ?? null) : null;
            $names = is_array($answer) ? ($answer['names_a_value'] ?? null) : null;
            if (! is_string($id) || ! is_bool($names) || ! isset($asked[$id]) || isset($read[$id])) {
                continue;
            }
            $read[$id] = true;
            if (! $names) {
                continue;
            }
            $named[$id] = true;
            $one = $asked[$id];
            $values = implode(', ', array_map(static fn (string $v): string => "«{$v}»", $one['values']));
            $violations[] = new LessonViolation(
                LessonCodes::NAMES_FILLER_MEANING,
                $id,
                "the reply names or paraphrases a filler of the frame: one general fact about the matter of the scene instead, true whatever was asked («{$one['reply']}» to «{$one['question']}» with {$values}, the seam judge says)",
            );
        }

        return new LessonSeamVerdict(
            LessonSeamVerdict::JUDGED, $violations, count($items), count($judged), $reply->costUsd, $reply->latencyMs,
            $replies !== [] && ! is_array($answers) ? 'no replies in the answer' : '', $sent, array_map('strval', array_keys($named)),
        );
    }
}
