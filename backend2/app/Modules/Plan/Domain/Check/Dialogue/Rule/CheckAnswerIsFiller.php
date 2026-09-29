<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `check.answer_is_filler` — a warning (CHECK PER EXCHANGE: «the check is ALWAYS about A's message … the options are never the
 * learner's answer or the fillers of the learner's frame»). The right option of an exchange's check is a filler of the frame
 * its learner line stands on — in the target language or in the native one ({@see StageText::normal()}).
 */
final class CheckAnswerIsFiller implements DialogueRule
{
    public const CODE = 'check.answer_is_filler';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        foreach ($dialogue->exchanges as $e) {
            $right = $e->exchange->check->correctOption();
            $phraseId = $e->exchange->learner()?->phraseId;
            $frame = $phraseId === null ? null : $context->skeleton->frame($phraseId);
            if ($right === null || $frame === null) {
                continue;
            }
            foreach ($frame->phrase->fillers() as $filler) {
                if (StageText::normal($right->textTarget) === StageText::normal($filler->target)
                    || StageText::normal($right->textNative) === StageText::normal($filler->native)) {
                    $out[] = new LessonViolation(self::CODE, 'x'.$e->step().'.check', "the right option «{$right->textTarget}» / «{$right->textNative}» is the learner's filler «{$filler->target}»");
                    break;
                }
            }
        }

        return $out;
    }
}
