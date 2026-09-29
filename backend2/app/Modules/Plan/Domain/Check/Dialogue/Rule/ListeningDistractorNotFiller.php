<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `listening.distractor_not_filler` — a warning (LISTENING: «a question about the learner's own value takes the wrong options
 * from that frame's OTHER fillers in the skeleton»). A question whose right option is the native value a learner line said
 * — a filler of its frame — has as its wrong options only the native values of that frame's other fillers.
 */
final class ListeningDistractorNotFiller implements DialogueRule
{
    public const CODE = 'listening.distractor_not_filler';

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
        $said = [];
        foreach ($dialogue->exchanges as $e) {
            $line = $e->exchange->learner();
            $frame = $line?->phraseId === null ? null : $context->skeleton->frame($line->phraseId)?->phrase;
            $filler = $frame?->filler($line?->filler);
            if ($frame !== null && $filler !== null) {
                $said[StageText::normal($filler->native)] = $frame;
            }
        }

        $out = [];
        foreach ($dialogue->listening as $index => $question) {
            $right = $question->correctOption();
            $frame = $right === null ? null : ($said[StageText::normal($right)] ?? null);
            if ($frame === null) {
                continue;
            }
            $values = array_map(static fn ($f): string => StageText::normal($f->native), $frame->fillers());
            foreach ($question->optionsNative as $i => $option) {
                if ($i !== $question->correctOptionIndex && ! in_array(StageText::normal($option), $values, true)) {
                    $out[] = new LessonViolation(self::CODE, 'L'.($index + 1), "«{$option}» is no other filler of {$frame->id}, whose value «{$right}» the question asks");
                    break;
                }
            }
        }

        return $out;
    }
}
