<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

/**
 * EVERY CODE THE LESSON VALIDATOR COUNTS (`lesson_day.v4.5`; наряды GEN-2a, GEN-2b и его доработка) — fifty. Every
 * breach is counted by code; seven of them are fatal — the day is not dealt until a repair takes their card
 * ({@see LessonGate}) — and the other 43 are warnings: counted and kept. One code is not the validator's but the seam
 * judge's — a model reads the native sentences a frame makes with its fillers ({@see JUDGED}).
 *
 * No code is about the speaking key: the key is the server's, taken from the frame (`docs/plan-v2.md` §3а).
 *
 * Two counters are no findings at all: a check that did not run for want of a language pack
 * ({@see LANG_PACK_MISSING}), and a seam judge that did not answer ({@see JUDGE_UNAVAILABLE}).
 *
 * Canon with the exact rule of every code — `docs/plan-v2.md` §4.
 */
final class LessonCodes
{
    // Shape of the visit.
    public const DIALOGUE_COUNT = 'dialogue.count';

    public const VOCAB_COUNT = 'vocab.count';

    public const EXCHANGE_SHAPE = 'exchange.shape';

    public const EXCHANGE_SECOND_QUESTION = 'exchange.second_question';

    public const EXCHANGE_REPEATS = 'exchange.repeats';

    public const CHECK_SHAPE = 'check.shape';

    public const LISTENING_SHAPE = 'listening.shape';

    public const PRONUNCIATION_SCRIPT = 'pronunciation.script';

    // Frames.
    public const FRAME_COUNT = 'frame.count';

    public const FRAME_UNUSED = 'frame.unused';

    public const FRAME_TOO_LONG = 'frame.too_long';

    public const FRAME_NO_SLOT_SHARE = 'frame.no_slot_share';

    public const FRAME_NATIVE_ALTERNATIVES = 'frame.native_alternatives';

    public const FRAME_NO_END_PUNCT = 'frame.no_end_punct';

    public const FRAME_NATIVE_PUNCT = 'frame.native_punct';

    public const FRAME_UNRESOLVED_PRONOUN = 'frame.unresolved_pronoun';

    public const FRAME_NATIVE_AGREEMENT = 'frame.native_agreement';

    // Fillers.
    public const FILLER_COUNT = 'filler.count';

    public const FILLER_UNGRAMMATICAL = 'filler.ungrammatical';

    public const FILLER_ONE_IN_DIALOGUE = 'filler.one_in_dialogue';

    public const FILLER_IS_CLAUSE = 'filler.is_clause';

    public const FILLER_ARTICLE_SEAM = 'filler.article_seam';

    public const FILLER_NATIVE_SEAM = 'filler.native_seam';

    // The learner's lines.
    public const LINE_NE_FRAME = 'line.ne_frame';

    public const LINE_TOO_LONG = 'line.too_long';

    public const LINE_NO_FRAME = 'line.no_frame';

    public const VARIANT_LONGER = 'variant.longer';

    public const LEARNER_RESTATES_PARTNER = 'learner.restates_partner';

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

    // Native text, image prompts.
    public const NATIVE_GENDERED_PAST = 'native.gendered_past';

    public const IMAGE_PROMPT_RULE_TEXT = 'image_prompt.rule_text';

    /** A check that did not run: the language of its side has no pack for it. One per code a validation skipped. */
    public const LANG_PACK_MISSING = 'lang.pack_missing';

    /** The seam judge was asked and gave no usable answer: the day's native seams went unread. */
    public const JUDGE_UNAVAILABLE = 'judge.unavailable';

    /** The codes a model finds, not the validator: the native seams, read by the seam judge once a day. */
    public const JUDGED = [self::FILLER_NATIVE_SEAM];

    /** @return list<string> every code, in the order the report lists them */
    public static function all(): array
    {
        return [
            self::DIALOGUE_COUNT, self::VOCAB_COUNT, self::EXCHANGE_SHAPE, self::EXCHANGE_SECOND_QUESTION, self::EXCHANGE_REPEATS,
            self::CHECK_SHAPE, self::LISTENING_SHAPE, self::PRONUNCIATION_SCRIPT,
            self::FRAME_COUNT, self::FRAME_UNUSED, self::FRAME_TOO_LONG, self::FRAME_NO_SLOT_SHARE,
            self::FRAME_NATIVE_ALTERNATIVES, self::FRAME_NO_END_PUNCT, self::FRAME_NATIVE_PUNCT, self::FRAME_UNRESOLVED_PRONOUN,
            self::FRAME_NATIVE_AGREEMENT,
            self::FILLER_COUNT, self::FILLER_UNGRAMMATICAL, self::FILLER_ONE_IN_DIALOGUE, self::FILLER_IS_CLAUSE,
            self::FILLER_ARTICLE_SEAM, self::FILLER_NATIVE_SEAM,
            self::LINE_NE_FRAME, self::LINE_TOO_LONG, self::LINE_NO_FRAME, self::VARIANT_LONGER,
            self::LEARNER_RESTATES_PARTNER,
            self::KIND_ASK_COUNT, self::KIND_RESCUE_COUNT, self::RESCUE_NOT_FIRST, self::RESCUE_NEW_FACT, self::RESCUE_NO_PREV,
            self::PARTNER_TWO_QUESTIONS, self::PARTNER_TOO_LONG, self::PARTNER_CLOSER,
            self::CHECK_ABOUT_LEARNER, self::CHECK_VERBATIM, self::CHECK_LISTED_ALTERNATIVE_AS_WRONG,
            self::LISTENING_COUNT, self::LISTENING_SAME_EXCHANGE, self::LISTENING_NO_LEARNER_VALUE, self::LISTENING_DISTRACTOR_NOT_FILLER,
            self::VOCAB_FREE_COMBINATION, self::VOCAB_EVERYDAY_WORD, self::VOCAB_USED_IN_WRONG, self::VOCAB_LEARNER_SHARE, self::VOCAB_NESTED,
            self::NATIVE_GENDERED_PAST, self::IMAGE_PROMPT_RULE_TEXT,
        ];
    }

    /** @return list<string> the codes the validator itself finds — every code but the judged ones */
    public static function validated(): array
    {
        return array_values(array_diff(self::all(), self::JUDGED));
    }
}
