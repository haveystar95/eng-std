<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * THE SLOT OF A QUESTION IS NOT ITS ANSWER (`plan-builder-v2.1`, STEP 4: «a question is written as a pattern whose slot is the
 * thing asked about … and never the answer the partner will give»: «ask how much the rent is — slot: the price» is wrong). By
 * the code's reading (наряд GEN-4), an «ask» item's slot is the answer when it is named by a word of what an answer IS — a
 * price, a time, a date, an amount, a schedule, a length — and the question asks how, when or what («ask how long the wait
 * is — slot: the wait time»). A slot the partner speaks ABOUT is no finding («ask whether a deposit is needed — slot: the
 * deposit» beside «says whether a deposit is needed»): that is the thing asked about — the first reading of the gate run of
 * GEN-4 flagged it, 51 times in 35 scenes, and was taken out. A warning.
 */
final class SurvivalSlotAnswerCheck implements BlueprintCheck
{
    /** The heads of a slot that name what the partner answers, not what the learner asks about. */
    public const ANSWER_HEADS = ['price', 'cost', 'fee', 'amount', 'sum', 'time', 'date', 'day', 'hour', 'schedule', 'duration', 'length', 'answer', 'result', 'number'];

    public function name(): string
    {
        return 'survival_slot_answer';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            foreach ($scene->survival->mustSay as $index => $item) {
                if (SurvivalWords::verb($item['text']) !== 'ask' || $item['slot'] === null) {
                    continue;
                }
                $heads = array_values(array_intersect(SurvivalWords::content($item['slot']), self::ANSWER_HEADS));
                if ($heads !== [] && preg_match('/\b(?:how|when|what)\b/iu', $item['text']) === 1) {
                    $out[] = "scene {$scene->order}: must_say ".($index + 1)." «{$item['text']}» — slot «{$item['slot']}» is what the answer names (".implode(', ', $heads).')';
                }
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}
