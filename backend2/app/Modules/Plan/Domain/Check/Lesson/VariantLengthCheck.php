<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Words;

/** A simplified variant is not longer than the original by more than one word and is not the original. */
final class VariantLengthCheck implements LessonCheck
{
    public function name(): string
    {
        return 'variant_length';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                foreach ($message->simplifiedVariants as $variant) {
                    if (! self::isAcceptable($variant, $message->textTarget)) {
                        $out[] = "exchange {$exchange->step}: variant «{$variant}» against «{$message->textTarget}»";
                    }
                }
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        return $lesson->withExchanges(array_map(
            static fn (Exchange $e): Exchange => $e->withMessages(array_map(
                static fn (Message $m): Message => $m->withVariants(array_values(array_filter(
                    $m->simplifiedVariants,
                    static fn (string $v): bool => self::isAcceptable($v, $m->textTarget),
                ))),
                $e->messages,
            )),
            $lesson->exchanges,
        ));
    }

    public static function isAcceptable(string $variant, string $original): bool
    {
        if (Words::tokens($variant) === Words::tokens($original)) {
            return false;
        }

        return Words::count($variant) <= Words::count($original) + 1;
    }
}
