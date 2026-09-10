<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Lesson\Phrase;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\NativeScript;

/**
 * A reading is written in the learner's own script. A foreign letter inside it is a reading the
 * learner cannot read; `drop` erases that unit's reading and the card goes on without it.
 */
final class PronunciationScriptCheck implements LessonCheck
{
    public function name(): string
    {
        return 'pronunciation_script';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach ($lesson->phrases as $phrase) {
            if (! NativeScript::isValidReading($context->nativeLang, $phrase->pronunciationNative)) {
                $out[] = "phrase {$phrase->id}: reading «{$phrase->pronunciationNative}» leaves the native script";
            }
        }
        foreach ($lesson->vocabulary as $item) {
            if (! NativeScript::isValidReading($context->nativeLang, $item->pronunciationNative)) {
                $out[] = "vocabulary {$item->id}: reading «{$item->pronunciationNative}» leaves the native script";
            }
        }
        foreach ($lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                if ($message->isLearner() && $message->pronunciationNative !== null
                    && ! NativeScript::isValidReading($context->nativeLang, $message->pronunciationNative)) {
                    $out[] = "exchange {$exchange->step}: reading «{$message->pronunciationNative}» leaves the native script";
                }
            }
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        $lang = $context->nativeLang;

        $phrases = array_map(
            static fn (Phrase $p): Phrase => NativeScript::isValidReading($lang, $p->pronunciationNative) ? $p : $p->withoutPronunciation(),
            $lesson->phrases,
        );
        $vocabulary = array_map(
            static fn (VocabularyItem $v): VocabularyItem => NativeScript::isValidReading($lang, $v->pronunciationNative) ? $v : $v->withoutPronunciation(),
            $lesson->vocabulary,
        );
        $exchanges = array_map(
            static fn (Exchange $e): Exchange => $e->withMessages(array_map(
                static fn (Message $m): Message => $m->pronunciationNative !== null && ! NativeScript::isValidReading($lang, $m->pronunciationNative)
                    ? $m->withoutPronunciation()
                    : $m,
                $e->messages,
            )),
            $lesson->exchanges,
        );

        return $lesson->withPhrases($phrases)->withVocabulary($vocabulary)->withExchanges($exchanges);
    }
}
