<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Service\Words;

/**
 * `check.verbatim` — a warning (CHECK PER EXCHANGE: «the correct option is a PARAPHRASE: it repeats no two consecutive words
 * of A's message»). The right option holds two words in a row of A's message of its exchange, in the same language.
 */
final class CheckVerbatim implements DialogueRule
{
    public const CODE = 'check.verbatim';

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
            $partner = $e->exchange->partner();
            if ($right === null || $partner === null) {
                continue;
            }
            foreach ([[$right->textTarget, $partner->textTarget], [$right->textNative, $partner->textNative]] as [$option, $line]) {
                $pair = self::sharedPair($option, $line);
                if ($pair !== null) {
                    $out[] = new LessonViolation(self::CODE, 'x'.$e->step().'.check', "the right option «{$option}» repeats «{$pair}» of A's «{$line}»");
                    break;
                }
            }
        }

        return $out;
    }

    /** The first two words in a row `$option` shares with `$line`, or null. */
    private static function sharedPair(string $option, string $line): ?string
    {
        $mine = Words::tokens($option);
        $theirs = Words::tokens($line);
        $pairs = [];
        for ($i = 0; $i + 1 < count($theirs); $i++) {
            $pairs[$theirs[$i].' '.$theirs[$i + 1]] = true;
        }
        for ($i = 0; $i + 1 < count($mine); $i++) {
            if (isset($pairs[$mine[$i].' '.$mine[$i + 1]])) {
                return $mine[$i].' '.$mine[$i + 1];
            }
        }

        return null;
    }
}
