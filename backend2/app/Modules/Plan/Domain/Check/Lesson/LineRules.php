<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\EnglishWords;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE LEARNER'S LINES (`lesson_day.v4.4`, LEARNER MESSAGES, SPEAKING SUPPORT, MOBILE LENGTH): every
 * answer/ask line stands on a frame and IS that frame with its filler (the server's assembly against
 * what the model wrote); ten words at most without the glue; a speaking key of one to four words that
 * stands in the line, carries a content word and no word of the filler; variants no longer than the
 * line and not the line.
 *
 * Keys and variants are judged on the SERVED line — the text the learner reads and says.
 */
final class LineRules implements LessonRule
{
    public const MAX_WORDS = 10;

    public const KEY_MAX_WORDS = 4;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($answer->exchanges as $exchange) {
            $message = $exchange->learner();
            if ($message === null) {
                continue;
            }
            $address = LessonViolation::learner($exchange->step);
            $phrase = $message->phraseId === null ? null : $answer->phrase($message->phraseId);
            $served = $message->textTarget;

            if ($exchange->kind->takesFrame()) {
                if ($message->phraseId === null) {
                    $out[] = new LessonViolation(LessonCodes::LINE_NO_FRAME, $address, "an {$exchange->kind->value} line without a frame");
                } elseif ($phrase === null) {
                    $out[] = new LessonViolation(LessonCodes::LINE_NO_FRAME, $address, "phrase_id {$message->phraseId} names no frame");
                } else {
                    $line = FrameText::line($phrase, $message->filler, $message->textTarget);
                    $served = $line['text'];
                    if (! $line['matches']) {
                        $out[] = new LessonViolation(
                            LessonCodes::LINE_NE_FRAME,
                            $address,
                            "«{$message->textTarget}» is not «{$phrase->frameTarget}» with «".($message->filler ?? 'null')."»; served as «{$served}»",
                        );
                    }
                }
            }

            $words = FrameText::wordsWithoutGlue($served);
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::LINE_TOO_LONG, $address, "«{$served}» has {$words} words without the glue (max ".self::MAX_WORDS.')');
            }

            $out = [...$out, ...self::key($exchange, $message, $phrase, $served, $context), ...self::variants($address, $message, $served)];
        }

        return $out;
    }

    /** @return list<LessonViolation> */
    private static function key(Exchange $exchange, Message $message, ?Phrase $phrase, string $served, LessonValidationContext $context): array
    {
        $address = LessonViolation::learner($exchange->step);
        $key = trim((string) $message->speakingKey);
        if ($key === '') {
            return [new LessonViolation(LessonCodes::KEY_NOT_IN_LINE, $address, 'no speaking key')];
        }
        $out = [];
        $positions = self::positions($served, $key);
        if ($positions === []) {
            $out[] = new LessonViolation(LessonCodes::KEY_NOT_IN_LINE, $address, "the key «{$key}» is not in «{$served}»");
        }
        if ($context->targetLang === 'en' && EnglishWords::content($key) === []) {
            $out[] = new LessonViolation(LessonCodes::KEY_NO_CONTENT_WORD, $address, "the key «{$key}» has no content word");
        }
        $keyWords = Words::count($key);
        if ($keyWords > self::KEY_MAX_WORDS) {
            $out[] = new LessonViolation(LessonCodes::KEY_TOO_LONG, $address, "the key «{$key}» has {$keyWords} words (1–".self::KEY_MAX_WORDS.')');
        }

        $span = $phrase === null || ! $exchange->kind->takesFrame() ? null : self::fillerSpan($phrase, $message->filler, $served);
        if ($span !== null && $positions !== []) {
            [$from, $to] = $span;
            $length = mb_strlen($key);
            $clear = array_filter($positions, static fn (int $at): bool => $at + $length <= $from || $at >= $to);
            if ($clear === []) {
                $out[] = new LessonViolation(LessonCodes::KEY_CONTAINS_FILLER, $address, "the key «{$key}» takes words of the filler «{$message->filler}»");
            }
        }

        return $out;
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

    /**
     * Where the filler stands in the served line, in characters: after the glue and the frame's text
     * before the slot. Null when the frame has no slot or the line is not the frame with this filler.
     *
     * @return array{0: int, 1: int}|null
     */
    private static function fillerSpan(Phrase $phrase, ?string $filler, string $served): ?array
    {
        if ($filler === null || ! FrameText::hasSlot($phrase->frameTarget)) {
            return null;
        }
        $core = FrameText::fill($phrase->frameTarget, $filler);
        $glue = mb_strlen($served) - mb_strlen($core);
        if ($glue < 0 || mb_strtolower(mb_substr($served, $glue)) !== mb_strtolower($core)) {
            return null;
        }
        $parts = preg_split(FrameText::SLOT_PATTERN, $phrase->frameTarget, 2);
        $from = $glue + mb_strlen(is_array($parts) ? ltrim($parts[0]) : '');

        return [$from, $from + mb_strlen($filler)];
    }

    /** @return list<int> every place `$needle` stands in `$text`, ignoring case, in characters */
    private static function positions(string $text, string $needle): array
    {
        $haystack = mb_strtolower($text);
        $needle = mb_strtolower($needle);
        $out = [];
        $offset = 0;
        while (($at = mb_strpos($haystack, $needle, $offset)) !== false) {
            $out[] = $at;
            $offset = $at + 1;
        }

        return $out;
    }
}
