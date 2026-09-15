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
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * THE LEARNER'S LINES (`lesson_day.v4.5`, LEARNER MESSAGES, MOBILE LENGTH, TEXT QUALITY): every answer/ask line stands
 * on a frame and IS that frame with one of its fillers — the server finds which one in the text, glue, one lowered
 * letter and the closing mark aside ({@see FrameText::line}); ten words at most without the glue; variants no longer
 * than the line and not the line; a line that reacts to the partner, never restates what the partner just said.
 *
 * The speaking key is the server's, taken from the frame (`docs/plan-v2.md` §3а) — no rule reads the model's key.
 *
 * «Restates» is read by words: an answer to a partner's STATEMENT that repeats at least three fifths of that
 * statement's words (a question's words come back in any answer to it). Variants are judged on the SERVED line — the
 * text the learner reads and says.
 */
final class LineRules implements LessonRule
{
    public const MAX_WORDS = 10;

    public const RESTATED_SHARE = 0.6;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $restates = $context->reads(LessonCodes::LEARNER_RESTATES_PARTNER, LanguageSide::Target, 'sentence_ends');

        $out = [];
        foreach ($answer->exchanges as $exchange) {
            $message = $exchange->learner();
            if ($message === null) {
                continue;
            }
            $address = LessonViolation::learner($exchange->step);
            $phrase = $message->phraseId === null ? null : $answer->phrase($message->phraseId);
            $served = trim($message->textTarget);

            if ($exchange->kind->takesFrame()) {
                if ($message->phraseId === null) {
                    $out[] = new LessonViolation(LessonCodes::LINE_NO_FRAME, $address, "an {$exchange->kind->value} line without a frame");
                } elseif ($phrase === null) {
                    $out[] = new LessonViolation(LessonCodes::LINE_NO_FRAME, $address, "phrase_id {$message->phraseId} names no frame");
                } elseif (! FrameText::line($phrase, $message->textTarget)['matches']) {
                    $out[] = new LessonViolation(LessonCodes::LINE_NE_FRAME, $address, self::notTheFrame($message, $phrase));
                }
            }

            $words = FrameText::wordsWithoutGlue($served);
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::LINE_TOO_LONG, $address, "«{$served}» has {$words} words without the glue (max ".self::MAX_WORDS.')');
            }

            $out = [...$out, ...self::variants($address, $message, $served)];

            if ($restates && $exchange->kind === ExchangeKind::Answer && ($partner = $exchange->partner()) !== null
                && ! $context->targetWords()->isQuestion($partner->textTarget)) {
                $theirs = array_values(array_unique(Words::tokens($partner->textTarget)));
                $repeated = array_values(array_intersect($theirs, Words::tokens($served)));
                if ($theirs !== [] && count($repeated) >= self::RESTATED_SHARE * count($theirs)) {
                    $out[] = new LessonViolation(
                        LessonCodes::LEARNER_RESTATES_PARTNER,
                        $address,
                        "«{$served}» repeats ".count($repeated).' of '.count($theirs).' words of the partner\'s «'.$partner->textTarget.'» ('.implode(', ', $repeated).')',
                    );
                }
            }
        }

        return $out;
    }

    private static function notTheFrame(Message $message, Phrase $phrase): string
    {
        if (! FrameText::hasSlot($phrase->frameTarget)) {
            return "«{$message->textTarget}» is not «{$phrase->frameTarget}»";
        }
        $fillers = array_map(static fn (Filler $f): string => "«{$f->target}»", $phrase->fillers());

        return "«{$message->textTarget}» is not «{$phrase->frameTarget}» with any of its fillers (".($fillers === [] ? 'it has none' : implode(', ', $fillers)).')';
    }

    /** @return list<LessonViolation> */
    private static function variants(string $address, Message $message, string $served): array
    {
        $out = [];
        $lineWords = Words::count($served);
        foreach ($message->simplifiedVariants as $variant) {
            if (Words::tokens($variant) === Words::tokens($served)) {
                $out[] = new LessonViolation(LessonCodes::VARIANT_LONGER, $address, "the variant «{$variant}» is the line itself");
            } elseif (Words::count($variant) > $lineWords) {
                $out[] = new LessonViolation(LessonCodes::VARIANT_LONGER, $address, 'the variant «'.$variant.'» has '.Words::count($variant)." words, the line {$lineWords}");
            }
        }

        return $out;
    }
}
