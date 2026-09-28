<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\Language\TextLanguage;
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
 * THE DAY'S WORDS (`lesson_day`, VOCABULARY): `used_in` names places that exist and really
 * carry the term; at least half the items stand in the learner's frames or fillers; no item inside
 * another; no free combination of ordinary words as a «chunk», no plain everyday word as a word; an abbreviation or an
 * acronym only when the learner's language has an everyday word for it (ATM → банкомат, PIN → ПИН-код), never one with
 * none (API, CI/CD, HR) — `vocab.abbreviation`, a warning (доработка GEN-3, решение архитектора): whether the learner's
 * language has such a word is the model's to judge by the prompt's rule, the code only counts the abbreviation.
 *
 * An abbreviation is read by its letters, in any language that has capitals: two capitals or more in a row, with or without
 * a dot or a slash between them — API, CI/CD, U.S.; one capital («X-ray», «iPhone») is not one. The free combination and
 * the everyday word read the target's pack — its ordinary heads, its everyday words and STOP LIST.
 *
 * THE DEFINITION IS IN THE TARGET LANGUAGE (`definition_target`, наряд LANG-1b §4): every ru→X day of the LANG-1 scouting
 * defined its German, Polish or French words in English. `vocab.definition_language`, read by {@see TextLanguage} with the
 * target's pack: fewer than half of the definition's letters are the target's letters (a Russian definition under a German
 * word), or — in the target's own letters — the definition holds more of the words only a neighbour language uses often
 * than of the words only the target does («pain in the throat» under a German word: one English word, «the», and no German
 * one). A definition of no frequent word at all says nothing and is let be. A warning in §4, FATAL since §10 (the owner's
 * ru→ro day defined every word in English): the word's card goes to P2R, which writes the definition anew and keeps the
 * word. On the 38 X→en days of the LANG-1b replay it found nothing — an English definition of an English word is no finding.
 */
final class VocabularyRules implements LessonRule
{
    /** Two capital letters or more in a row, a dot or a slash allowed between them, and no lower-case letter after them. */
    public const ABBREVIATION = '~(?<!\p{L})\p{Lu}(?:[./]?\p{Lu})+\.?(?!\p{Ll})~u';

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $combinations = $context->reads(LessonCodes::VOCAB_FREE_COMBINATION, LanguageSide::Target, 'ordinary_heads', 'everyday_words', 'function_words', 'word_forms');
        $everyday = $context->reads(LessonCodes::VOCAB_EVERYDAY_WORD, LanguageSide::Target, 'everyday_words');
        $definitions = $context->reads(LessonCodes::VOCAB_DEFINITION_LANGUAGE, LanguageSide::Target, 'script_letters', 'common_words');
        $words = $context->targetWords();

        $out = [];
        $learnerItems = 0;
        foreach ($answer->vocabulary as $item) {
            [$wrong, $inFrames] = self::usedIn($answer, $item);
            $out = [...$out, ...$wrong];
            if ($inFrames) {
                $learnerItems++;
            }

            if (self::isAbbreviation($item->termTarget)) {
                $out[] = new LessonViolation(LessonCodes::VOCAB_ABBREVIATION, $item->id, "«{$item->termTarget}» is an abbreviation or an acronym — a word of the day only when the learner's language has an everyday word for it");
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
            $foreign = $definitions ? self::definitionLanguage($item, $context->target) : null;
            if ($foreign !== null) {
                $out[] = new LessonViolation(LessonCodes::VOCAB_DEFINITION_LANGUAGE, $item->id, $foreign);
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

    public static function isAbbreviation(string $term): bool
    {
        return preg_match(self::ABBREVIATION, $term) === 1;
    }

    /**
     * Why the item's definition is not in the target language — its letters, or the frequent words of a neighbour it holds
     * more of than the target's own — or null when it reads as the target's, or says nothing either way.
     */
    private static function definitionLanguage(VocabularyItem $item, LanguagePack $target): ?string
    {
        $definition = trim($item->definitionTarget);
        if ($definition === '') {
            return null;
        }
        if (TextLanguage::outOfScript($definition, $target) === true) {
            return "the definition «{$definition}» of «{$item->termTarget}» is not written in the letters of the target language ({$target->code})";
        }
        foreach (TextLanguage::tellingWords($definition, $target) as $row) {
            if ($row['theirs'] > $row['mine']) {
                $said = implode(', ', $row['their_words']);

                return "the definition «{$definition}» of «{$item->termTarget}» reads as {$row['code']}, not {$target->code} ({$said})";
            }
        }

        return null;
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
