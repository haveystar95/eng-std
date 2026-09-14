<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/**
 * EVERY CODE THE LESSON VALIDATOR COUNTS (`lesson_day.v4.4`; наряд GEN-2a). Nothing here is fatal:
 * a lesson is accepted whatever it breaks, every breach is counted by code, and which codes become
 * fatal is decided by the architect after live lessons, not in this file.
 *
 * Canon with the exact rule of every code — `docs/plan-v2.md` §4.
 */
final class LessonCodes
{
    // Shape of the visit (added to the наряд's list: without them a short or broken answer counts nothing).
    public const DIALOGUE_COUNT = 'dialogue.count';

    public const VOCAB_COUNT = 'vocab.count';

    public const EXCHANGE_SHAPE = 'exchange.shape';

    public const EXCHANGE_SECOND_QUESTION = 'exchange.second_question';

    public const CHECK_SHAPE = 'check.shape';

    public const LISTENING_SHAPE = 'listening.shape';

    public const PRONUNCIATION_SCRIPT = 'pronunciation.script';

    // Frames (v4.4 adds the count rule and «every frame is said»).
    public const FRAME_COUNT = 'frame.count';

    public const FRAME_UNUSED = 'frame.unused';

    public const FRAME_TOO_LONG = 'frame.too_long';

    public const FRAME_NO_SLOT_SHARE = 'frame.no_slot_share';

    public const FRAME_NATIVE_ALTERNATIVES = 'frame.native_alternatives';

    public const FRAME_NATIVE_PUNCT = 'frame.native_punct';

    // Fillers.
    public const FILLER_COUNT = 'filler.count';

    public const FILLER_UNGRAMMATICAL = 'filler.ungrammatical';

    public const FILLER_ONE_IN_DIALOGUE = 'filler.one_in_dialogue';

    // The learner's lines.
    public const LINE_NE_FRAME = 'line.ne_frame';

    public const LINE_TOO_LONG = 'line.too_long';

    public const LINE_NO_FRAME = 'line.no_frame';

    public const KEY_NOT_IN_LINE = 'key.not_in_line';

    public const KEY_CONTAINS_FILLER = 'key.contains_filler';

    public const KEY_NO_CONTENT_WORD = 'key.no_content_word';

    public const KEY_TOO_LONG = 'key.too_long';

    public const VARIANT_LONGER = 'variant.longer';

    // Kinds of exchange.
    public const KIND_ASK_COUNT = 'kind.ask_count';

    public const KIND_RESCUE_COUNT = 'kind.rescue_count';

    public const RESCUE_NOT_FIRST = 'rescue.not_first';

    public const RESCUE_NEW_FACT = 'rescue.new_fact';

    public const RESCUE_NO_PREV = 'rescue.no_prev';

    // The partner.
    public const PARTNER_TWO_QUESTIONS = 'partner.two_questions';

    public const PARTNER_TOO_LONG = 'partner.too_long';

    public const PARTNER_CLOSER = 'partner.closer';

    // Checks.
    public const CHECK_ABOUT_LEARNER = 'check.about_learner';

    public const CHECK_VERBATIM = 'check.verbatim';

    public const CHECK_LISTED_ALTERNATIVE_AS_WRONG = 'check.listed_alternative_as_wrong';

    // Listening.
    public const LISTENING_COUNT = 'listening.count';

    public const LISTENING_SAME_EXCHANGE = 'listening.same_exchange';

    public const LISTENING_NO_LEARNER_VALUE = 'listening.no_learner_value';

    public const LISTENING_DISTRACTOR_NOT_FILLER = 'listening.distractor_not_filler';

    // Vocabulary.
    public const VOCAB_FREE_COMBINATION = 'vocab.free_combination';

    public const VOCAB_EVERYDAY_WORD = 'vocab.everyday_word';

    public const VOCAB_USED_IN_WRONG = 'vocab.used_in_wrong';

    public const VOCAB_LEARNER_SHARE = 'vocab.learner_share';

    public const VOCAB_NESTED = 'vocab.nested';

    // Native text, image prompts, answer places.
    public const NATIVE_GENDERED_PAST = 'native.gendered_past';

    public const IMAGE_PROMPT_RULE_TEXT = 'image_prompt.rule_text';

    public const ANSWER_INDEX_SKEW = 'answer.index_skew';

    /** @return list<string> every code, in the order the report lists them */
    public static function all(): array
    {
        return [
            self::DIALOGUE_COUNT, self::VOCAB_COUNT, self::EXCHANGE_SHAPE, self::EXCHANGE_SECOND_QUESTION,
            self::CHECK_SHAPE, self::LISTENING_SHAPE, self::PRONUNCIATION_SCRIPT,
            self::FRAME_COUNT, self::FRAME_UNUSED, self::FRAME_TOO_LONG, self::FRAME_NO_SLOT_SHARE,
            self::FRAME_NATIVE_ALTERNATIVES, self::FRAME_NATIVE_PUNCT,
            self::FILLER_COUNT, self::FILLER_UNGRAMMATICAL, self::FILLER_ONE_IN_DIALOGUE,
            self::LINE_NE_FRAME, self::LINE_TOO_LONG, self::LINE_NO_FRAME,
            self::KEY_NOT_IN_LINE, self::KEY_CONTAINS_FILLER, self::KEY_NO_CONTENT_WORD, self::KEY_TOO_LONG, self::VARIANT_LONGER,
            self::KIND_ASK_COUNT, self::KIND_RESCUE_COUNT, self::RESCUE_NOT_FIRST, self::RESCUE_NEW_FACT, self::RESCUE_NO_PREV,
            self::PARTNER_TWO_QUESTIONS, self::PARTNER_TOO_LONG, self::PARTNER_CLOSER,
            self::CHECK_ABOUT_LEARNER, self::CHECK_VERBATIM, self::CHECK_LISTED_ALTERNATIVE_AS_WRONG,
            self::LISTENING_COUNT, self::LISTENING_SAME_EXCHANGE, self::LISTENING_NO_LEARNER_VALUE, self::LISTENING_DISTRACTOR_NOT_FILLER,
            self::VOCAB_FREE_COMBINATION, self::VOCAB_EVERYDAY_WORD, self::VOCAB_USED_IN_WRONG, self::VOCAB_LEARNER_SHARE, self::VOCAB_NESTED,
            self::NATIVE_GENDERED_PAST, self::IMAGE_PROMPT_RULE_TEXT, self::ANSWER_INDEX_SKEW,
        ];
    }
}
