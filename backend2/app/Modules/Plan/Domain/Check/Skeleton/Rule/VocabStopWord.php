<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\Words;

/**
 * `vocab.stop_word` — a warning (VOCABULARY: never a number, a plain everyday word). A word of the day whose one content word
 * is on the target's STOP LIST — its pack's `everyday_words` (numbers, family, time, colours, be / have / go and the plainest
 * words), a number or a time word by the pack's patterns — or a word that is nothing but a function word (a pronoun). A
 * number is a word whose every part between its hyphens is one («twenty-one», «3:30»), not a word with a digit in it
 * («COVID-19»).
 */
final class VocabStopWord implements SkeletonRule
{
    public const CODE = 'vocab.stop_word';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        $words = $context->targetReading('function_words', 'everyday_words', 'number_pattern', 'time_pattern');
        if ($words === null) {
            return [];
        }
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            $tokens = Words::tokens($item->termTarget);
            $content = array_values(array_filter($tokens, static fn (string $t): bool => ! $words->isFunction($t)));
            $stop = match (true) {
                $tokens !== [] && $content === [] => $tokens[0],
                count($content) === 1 && ($words->isEveryday($content[0]) || self::isNumber($content[0], $words) || $words->isTime($content[0])) => $content[0],
                default => null,
            };
            if ($stop !== null) {
                $out[] = new LessonViolation(self::CODE, $item->id, "«{$item->termTarget}» is a word of the stop list («{$stop}»)");
            }
        }

        return $out;
    }

    private static function isNumber(string $token, LanguageWords $words): bool
    {
        $parts = array_values(array_filter(explode('-', $token), static fn (string $part): bool => $part !== ''));

        return $parts !== [] && array_filter($parts, static fn (string $part): bool => ! $words->isNumber($part)) === [];
    }
}
