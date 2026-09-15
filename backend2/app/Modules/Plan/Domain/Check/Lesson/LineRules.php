<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * THE LEARNER'S LINES (`lesson_day.v4.5`, LEARNER MESSAGES, SPEAKING SUPPORT, MOBILE LENGTH, TEXT QUALITY): every
 * answer/ask line stands on a frame and IS that frame with its filler (the server's assembly against what the
 * model wrote); ten words at most without the glue; a speaking key of one to four words that stands in the line
 * and holds no word of the filler; variants no longer than the line and not the line; a line that reacts to the
 * partner, never restates what the partner just said.
 *
 * The key's content word (v4.5): a key holds a content word when the frame part outside the slot has one; when it
 * has none («Here is my ___», «What is ___?»), the key is the frame part up to the slot, as written. Content words
 * are the target's pack's.
 *
 * «Restates» is read by words: an answer to a partner's STATEMENT that repeats at least three fifths of that
 * statement's words (a question's words come back in any answer to it). Keys and variants are judged on the
 * SERVED line — the text the learner reads and says.
 */
final class LineRules implements LessonRule
{
    public const MAX_WORDS = 10;

    public const KEY_MAX_WORDS = 4;

    public const RESTATED_SHARE = 0.6;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $contentKeys = $context->reads(LessonCodes::KEY_NO_CONTENT_WORD, LanguageSide::Target, 'function_words', 'word_forms');
        $restates = $context->reads(LessonCodes::LEARNER_RESTATES_PARTNER, LanguageSide::Target, 'sentence_ends');

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

            $frame = $exchange->kind->takesFrame() ? $phrase : null;
            $out = [
                ...$out,
                ...self::key($exchange, $message, $frame, $served, $contentKeys ? $context : null),
                ...self::variants($address, $message, $served),
            ];

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

    /** @return list<LessonViolation> */
    private static function key(Exchange $exchange, Message $message, ?Phrase $frame, string $served, ?LessonValidationContext $content): array
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
        if ($content !== null) {
            $out = [...$out, ...self::contentWord($address, $key, $frame, $served, $content)];
        }
        $keyWords = Words::count($key);
        if ($keyWords > self::KEY_MAX_WORDS) {
            $out[] = new LessonViolation(LessonCodes::KEY_TOO_LONG, $address, "the key «{$key}» has {$keyWords} words (1–".self::KEY_MAX_WORDS.')');
        }

        $span = $frame === null ? null : self::fillerSpan($frame, $message->filler, $served);
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

    /**
     * The key's content word, v4.5: a key with none is right only when the frame part outside the slot has none
     * either and the key is that part up to the slot. A line with no frame (a rescue) is its own frame part.
     *
     * @return list<LessonViolation>
     */
    private static function contentWord(string $address, string $key, ?Phrase $frame, string $served, LessonValidationContext $context): array
    {
        $words = $context->targetWords();
        if ($words->content($key) !== []) {
            return [];
        }
        $pattern = $frame->frameTarget ?? $served;
        $part = (string) preg_replace(FrameText::SLOT_PATTERN, ' ', $pattern);
        if ($words->content($part) !== []) {
            return [new LessonViolation(LessonCodes::KEY_NO_CONTENT_WORD, $address, "the key «{$key}» has no content word, and «{$pattern}» has one")];
        }
        $upToSlot = trim((string) (preg_split(FrameText::SLOT_PATTERN, $pattern, 2) ?: [''])[0]);
        if (Words::tokens($key) !== Words::tokens($upToSlot)) {
            return [new LessonViolation(LessonCodes::KEY_NO_CONTENT_WORD, $address, "«{$pattern}» has no content word outside the slot: the key is «{$upToSlot}», the frame up to the slot, not «{$key}»")];
        }

        return [];
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
