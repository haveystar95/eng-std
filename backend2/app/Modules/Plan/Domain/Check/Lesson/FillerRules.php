<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE FILLERS (`lesson_day.v4.4`, FILLERS): two or three per slot; every one of them makes a
 * sentence when put into the frame; `in_dialogue` marks exactly the fillers the dialogue says — one
 * per exchange that uses the frame, two different ones for a frame said twice.
 *
 * «Grammatical» is checked by the assembly only mechanically — a filler with its own full stop, a
 * word doubled at the seam, «a» before a vowel, an article after an article, a whole clause where
 * the frame already has its verb («My biggest strength is ___» + «I am patient»). The rest is read by
 * a human: the day's export prints every frame with every filler put in.
 */
final class FillerRules implements LessonRule
{
    private const ARTICLES = ['a', 'an', 'the'];

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($answer->phrases as $phrase) {
            $marked = FrameText::hasSlot($phrase->frameTarget);
            if ($marked && $phrase->slot === null) {
                $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "«{$phrase->frameTarget}» has a slot but no fillers");
            } elseif (! $marked && $phrase->slot !== null) {
                $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "«{$phrase->frameTarget}» has fillers but no ___");
            } elseif ($phrase->slot !== null) {
                $count = count($phrase->slot->fillers);
                if ($count < 2 || $count > 3) {
                    $out[] = new LessonViolation(LessonCodes::FILLER_COUNT, $phrase->id, "{$count} fillers (2–3)");
                }
            }

            if ($marked) {
                foreach ($phrase->fillers() as $index => $filler) {
                    foreach (self::seams($phrase->frameTarget, $filler->target) as $problem) {
                        $out[] = new LessonViolation(
                            LessonCodes::FILLER_UNGRAMMATICAL,
                            $phrase->id.'.f'.($index + 1),
                            '«'.FrameText::fill($phrase->frameTarget, $filler->target)."»: {$problem}",
                        );
                    }
                }
            }

            $out = [...$out, ...self::marks($answer, $phrase)];
        }

        return $out;
    }

    /**
     * What the assembly of one filler into its frame shows on its face.
     *
     * @return list<string>
     */
    public static function seams(string $frame, string $filler): array
    {
        $problems = [];
        $filler = trim($filler);
        if (preg_match('/[.?!,;:]$/u', $filler) === 1) {
            $problems[] = 'the filler carries its own punctuation';
        }
        if (FrameText::hasSlot($filler)) {
            $problems[] = 'the filler holds a slot of its own';
        }

        $parts = preg_split(FrameText::SLOT_PATTERN, $frame, 2);
        $before = Words::tokens($parts[0] ?? '');
        $after = Words::tokens($parts[1] ?? '');
        $own = Words::tokens($filler);
        if ($own === []) {
            return [...$problems, 'the filler is empty'];
        }
        $left = $before === [] ? null : $before[count($before) - 1];
        $right = $after[0] ?? null;

        if (($left !== null && $left === $own[0]) || ($right !== null && $right === $own[count($own) - 1])) {
            $problems[] = 'a word is doubled at the seam';
        }
        if ($left !== null && in_array($left, self::ARTICLES, true) && in_array($own[0], self::ARTICLES, true)) {
            $problems[] = "«{$left} {$own[0]}» — an article after an article";
        }
        // An initialism is read by its letters («an MRI», «an X-ray»), and «one» starts with a «w»: both left alone.
        $spelled = preg_match('/^(?:[A-Z]{2,}|[A-Z]-)/u', $filler) === 1;
        if (! $spelled && $left === 'a' && preg_match('/^[aeio]/', $own[0]) === 1 && ! str_starts_with($own[0], 'one')) {
            $problems[] = "«a {$own[0]}» before a vowel";
        }
        if (! $spelled && $left === 'an' && preg_match('/^[bcdfgjklmnpqrstvwyz]/', $own[0]) === 1) {
            $problems[] = "«an {$own[0]}» before a consonant";
        }
        if (preg_match("/^(i|we|he|she|they|it|you)\s+(am|is|are|was|were|have|has|had|do|does|did|can|will)\b|^(i'm|it's|we're|they're|he's|she's|you're)\b/iu", $filler) === 1
            && $before !== []) {
            $problems[] = 'the filler is a whole clause, and the frame already has its verb';
        }

        return $problems;
    }

    /** @return list<LessonViolation> */
    private static function marks(Lesson $answer, Phrase $phrase): array
    {
        if ($phrase->slot === null) {
            return [];
        }
        $out = [];
        $said = [];
        foreach ($answer->linesOf($phrase->id) as $use) {
            $message = $use['message'];
            $step = $use['exchange']->step;
            $filler = $phrase->filler($message->filler);
            if ($filler === null) {
                $out[] = new LessonViolation(
                    LessonCodes::FILLER_ONE_IN_DIALOGUE,
                    LessonViolation::learner($step),
                    '«'.($message->filler ?? 'null')."» is not one of {$phrase->id}'s fillers",
                );

                continue;
            }
            $index = 0;
            foreach ($phrase->slot->fillers as $position => $candidate) {
                if ($candidate === $filler) {
                    $index = $position;
                    break;
                }
            }
            if (isset($said[$index])) {
                $out[] = new LessonViolation(
                    LessonCodes::FILLER_ONE_IN_DIALOGUE,
                    $phrase->id,
                    "exchanges {$said[$index]} and {$step} say {$phrase->id} with the same filler «{$filler->target}»",
                );

                continue;
            }
            $said[$index] = $step;
        }

        foreach ($phrase->slot->fillers as $index => $filler) {
            $address = $phrase->id.'.f'.($index + 1);
            if ($filler->inDialogue && ! isset($said[$index])) {
                $out[] = new LessonViolation(LessonCodes::FILLER_ONE_IN_DIALOGUE, $address, "«{$filler->target}» is marked in_dialogue, but no line says it");
            } elseif (! $filler->inDialogue && isset($said[$index])) {
                $out[] = new LessonViolation(LessonCodes::FILLER_ONE_IN_DIALOGUE, $address, "«{$filler->target}» is said in exchange {$said[$index]}, but not marked in_dialogue");
            }
        }

        return $out;
    }
}
