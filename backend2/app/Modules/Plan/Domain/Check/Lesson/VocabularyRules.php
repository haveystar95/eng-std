<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE DAY'S WORDS (`lesson_day.v4.5`, VOCABULARY): `used_in` names places that exist and really
 * carry the term; at least half the items stand in the learner's frames or fillers; no item inside
 * another; no free combination of ordinary words as a «chunk», no plain everyday word as a word.
 *
 * The last two read the target's pack — its ordinary heads, its everyday words and STOP LIST.
 */
final class VocabularyRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $combinations = $context->reads(LessonCodes::VOCAB_FREE_COMBINATION, LanguageSide::Target, 'ordinary_heads', 'everyday_words', 'function_words', 'word_forms');
        $everyday = $context->reads(LessonCodes::VOCAB_EVERYDAY_WORD, LanguageSide::Target, 'everyday_words');
        $words = $context->targetWords();

        $out = [];
        $learnerItems = 0;
        foreach ($answer->vocabulary as $item) {
            [$wrong, $inFrames] = self::usedIn($answer, $item);
            $out = [...$out, ...$wrong];
            if ($inFrames) {
                $learnerItems++;
            }

            $tokens = Words::tokens($item->termTarget);
            if ($combinations && $item->kind === VocabularyItem::KIND_CHUNK) {
                $content = $words->content($item->termTarget);
                if ((count($tokens) === 2 && $words->isOrdinaryHead($tokens[0]))
                    || ($content !== [] && array_filter($content, static fn (string $w): bool => ! $words->isEveryday($w)) === [])) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_FREE_COMBINATION, $item->id, "«{$item->termTarget}» is a free combination of ordinary words");
                }
            }
            if ($everyday && $item->kind === VocabularyItem::KIND_WORD && count($tokens) === 1 && $words->isEveryday($tokens[0])) {
                $out[] = new LessonViolation(LessonCodes::VOCAB_EVERYDAY_WORD, $item->id, "«{$item->termTarget}» is a plain everyday word or a word of the STOP LIST");
            }
        }

        $items = count($answer->vocabulary);
        if ($items > 0 && $learnerItems * 2 < $items) {
            $out[] = new LessonViolation(LessonCodes::VOCAB_LEARNER_SHARE, 'lesson', "{$learnerItems} of {$items} items are in the learner's frames or fillers (at least half)");
        }

        foreach ($answer->vocabulary as $short) {
            foreach ($answer->vocabulary as $long) {
                if ($short->id !== $long->id
                    && Words::count($short->termTarget) < Words::count($long->termTarget)
                    && Words::containsTerm($short->termTarget, $long->termTarget)) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_NESTED, $short->id, "«{$short->termTarget}» is inside «{$long->termTarget}» ({$long->id})");
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * What is wrong with an item's `used_in`, and whether it names a frame that carries the term.
     *
     * @return array{0: list<LessonViolation>, 1: bool}
     */
    private static function usedIn(Lesson $answer, VocabularyItem $item): array
    {
        if ($item->usedIn === []) {
            return [[new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, 'used_in is empty')], false];
        }
        $out = [];
        $inFrames = false;
        foreach ($item->usedIn as $ref) {
            if (preg_match('/^p\d+$/', $ref) === 1) {
                $phrase = $answer->phrase($ref);
                if ($phrase === null) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, "used_in names {$ref}, which is no frame");

                    continue;
                }
                // The frame, each filler, and the frame said with each filler: «work from home» is «I work ___» with «from home».
                $texts = [
                    $phrase->frameTarget,
                    ...array_map(static fn (Filler $f): string => $f->target, $phrase->fillers()),
                    ...array_map(static fn (Filler $f): string => FrameText::fill($phrase->frameTarget, $f->target), $phrase->fillers()),
                ];
                if (array_filter($texts, static fn (string $t): bool => Words::containsTerm($item->termTarget, $t)) === []) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, "«{$item->termTarget}» is not in frame {$ref} or its fillers");

                    continue;
                }
                $inFrames = true;
            } elseif (preg_match('/^A(\d+)$/', $ref, $m) === 1) {
                $partner = $answer->exchange((int) $m[1])?->partner();
                if ($partner === null) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, "used_in names {$ref}, which is no partner line");
                } elseif (! Words::containsTerm($item->termTarget, $partner->textTarget)) {
                    $out[] = new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, "«{$item->termTarget}» is not in the partner's line of exchange {$m[1]}");
                }
            } else {
                $out[] = new LessonViolation(LessonCodes::VOCAB_USED_IN_WRONG, $item->id, "used_in «{$ref}» is neither a frame nor a partner line");
            }
        }

        return [$out, $inFrames];
    }
}
