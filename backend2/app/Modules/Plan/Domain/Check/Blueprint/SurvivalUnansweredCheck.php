<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * EVERY QUESTION OF THE PARTNER HAS ITS ANSWER IN `must_say` (`plan-builder-v2.1`, STEP 4: «every question here has its answer
 * in must_say»). By the code's reading, not by meaning (наряд GEN-4; the GEN-4a script only listed the questions for the
 * reader): a `must_understand` item that starts with «asks» is answered when some `must_say` item that is no question of the
 * learner's — it does not start with «ask» — shares a content word with it, its slot included ({@see SurvivalWords}):
 * «asks where you worked before and for how long» is answered by «say where you worked before — slot: the workplace». A
 * warning: a question worded otherwise than its answer («what brings you in» — «say why you are here») is found here too.
 */
final class SurvivalUnansweredCheck implements BlueprintCheck
{
    public function name(): string
    {
        return 'survival_unanswered';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $answers = array_values(array_filter(
                $scene->survival->mustSay,
                static fn (array $item): bool => SurvivalWords::verb($item['text']) !== 'ask',
            ));
            foreach ($scene->survival->mustUnderstand as $index => $item) {
                if (SurvivalWords::verb($item) !== 'asks') {
                    continue;
                }
                $answered = array_filter($answers, static fn (array $answer): bool => SurvivalWords::share($item, $answer['text'].' '.($answer['slot'] ?? '')));
                if ($answered === []) {
                    $out[] = "scene {$scene->order}: must_understand ".($index + 1)." «{$item}» has no answer in must_say";
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
