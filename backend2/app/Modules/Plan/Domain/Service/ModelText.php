<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Shared\Domain\Service\TextNormalizer;

/**
 * THE MODEL'S TEXT AS THE PLAN KEEPS IT (наряд LANG-1b §6): every string of an answer — a plan, a scene, a line, a word, a
 * repaired card, a line of the talk, a judge's reason — without the characters that print nothing
 * ({@see TextNormalizer::visible()}). On 26.09 a live plan's day was titled «Опы\u{0004}т и навыки»: the model had put a
 * control character inside a word, the validator read past it, and the phone drew an empty box. Read here, at the one
 * place every answer of the plan's model comes in (`ContentModelPlanBuilder`), so no rule, no store and no screen ever
 * sees one. What is already stored is cleaned by `plan:clean-text`.
 */
final class ModelText
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> the same payload, every string in it visible
     */
    public static function visible(array $payload): array
    {
        $normalizer = new TextNormalizer;
        array_walk_recursive($payload, static function (mixed &$value) use ($normalizer): void {
            if (is_string($value)) {
                $value = $normalizer->visible($value);
            }
        });

        return $payload;
    }
}
