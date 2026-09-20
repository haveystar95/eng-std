<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE REGISTRY OF DAY TRAINERS (наряд SESSION-1a, разд. 1): twenty-nine kinds, twenty-eight of them dealt —
 * `listen_pairs` is reserved in the enum and never dealt (the lesson has no two alike lines to pair; its source is
 * v4.6). `phrase_own_slot` is gone (наряд FIX-2, п. 5): «своё окно» became the last round of «Скажи целиком»
 * (`phrase_other_slot`), which is now the one way a frame with a window is said aloud, at every level.
 * `recall_scenes` is new (наряд CONV-1): the rehearsal's «Вспомни свои реплики», read through, never graded.
 *
 * Every kind belongs to exactly one stage and to exactly one way of being counted, because the way it is counted is
 * what the server lets the client write:
 *
 * - CHOICE — a choice or an assembly the client grades without the network: right, right with a hint, wrong (the
 *   first wrong one comes back at the end of the stage, the second marks the unit to return), or given up on;
 * - SPOKEN — said aloud and passed by coverage: two attempts without a pass are a skip, not a failure — the
 *   recogniser's silence is no evidence of a lapse. Except for a PHRASE said aloud (`phrase_repeat`,
 *   `phrase_other_slot`; SESSION-1d, DECISIONS п. 327): given up on after two attempts, with a microphone, it is a
 *   lapse of its frame like a wrong choice — see {@see lapses()};
 * - JUDGED — the slot said aloud and judged by meaning: only the server writes the pass (`…/judge`), the client may
 *   only give up;
 * - WALKTHROUGH — read, listened to or tapped through: walked or skipped, never right or wrong.
 */
enum CardKind: string
{
    case WordIntro = 'word_intro';
    case WordRepeat = 'word_repeat';
    case WordChoose = 'word_choose';
    case WordListen = 'word_listen';
    case WordAssemble = 'word_assemble';
    case WordInLine = 'word_in_line';

    case PhraseIntro = 'phrase_intro';
    case PhraseAssemble = 'phrase_assemble';
    case PhraseChooseBack = 'phrase_choose_back';
    case PhraseSlot = 'phrase_slot';
    case PhraseSlotListen = 'phrase_slot_listen';
    case PhraseRepeat = 'phrase_repeat';
    case PhraseOtherSlot = 'phrase_other_slot';
    case PhraseCombine = 'phrase_combine';

    case DialoguePartner = 'dialogue_partner';
    case DialogueAnswer = 'dialogue_answer';
    case DialogueAsk = 'dialogue_ask';
    case DialogueRescue = 'dialogue_rescue';

    case ListenDialogue = 'listen_dialogue';
    case ListenQuestion = 'listen_question';
    case ListenReview = 'listen_review';
    case ListenPairs = 'listen_pairs';
    case ListenPredict = 'listen_predict';
    case ListenPace = 'listen_pace';
    case ListenNumber = 'listen_number';

    case SpeakAnswer = 'speak_answer';
    case SpeakEcho = 'speak_echo';
    case SpeakRetell = 'speak_retell';

    /** «Вспомни свои реплики» (кадр 37-3, наряд CONV-1): the rehearsal's own lines read through once, scene by scene. */
    case RecallScenes = 'recall_scenes';

    /** The attempts after which a phrase said aloud and given up on is a lapse, not the learner's will (п. 327). */
    public const SPOKEN_LAPSE_ATTEMPTS = 2;

    /**
     * The stage this kind is walked in. ONE kind reads its stage off the DAY as well as off itself:
     * «Повтори свою реплику» (`speak_retell`) stands in «Говорю сам» on a scene day and in
     * «Вспомнить» on the rehearsal (наряд CONV-1) — the same trainer, the same screen (кадр 35-4),
     * two different places in two different days. Everything else has one stage and one only.
     */
    public function stage(?DayType $day = null): Stage
    {
        if ($day === DayType::Rehearsal && $this === self::SpeakRetell) {
            return Stage::Recall;
        }

        return match ($this) {
            self::WordIntro, self::WordRepeat, self::WordChoose, self::WordListen, self::WordAssemble, self::WordInLine => Stage::Words,
            self::PhraseIntro, self::PhraseAssemble, self::PhraseChooseBack, self::PhraseSlot, self::PhraseSlotListen,
            self::PhraseRepeat, self::PhraseOtherSlot, self::PhraseCombine => Stage::Phrases,
            self::DialoguePartner, self::DialogueAnswer, self::DialogueAsk, self::DialogueRescue => Stage::Dialogue,
            self::ListenDialogue, self::ListenQuestion, self::ListenReview, self::ListenPairs, self::ListenPredict,
            self::ListenPace, self::ListenNumber => Stage::Listen,
            self::SpeakAnswer, self::SpeakEcho, self::SpeakRetell => Stage::Speak,
            self::RecallScenes => Stage::Recall,
        };
    }

    /** Reserved kinds stay in the enum (and the contract's enum) without being dealt: `listen_pairs` has no source yet. */
    public function isDealt(): bool
    {
        return $this !== self::ListenPairs;
    }

    /** A choice or an assembly, graded by the client: the only kind whose failure has consequences. */
    public function isChoice(): bool
    {
        return in_array($this, [
            self::WordChoose, self::WordListen, self::WordAssemble, self::WordInLine,
            self::PhraseAssemble, self::PhraseChooseBack, self::PhraseSlot, self::PhraseSlotListen, self::PhraseCombine,
            self::DialoguePartner,
            self::ListenQuestion, self::ListenPairs, self::ListenPredict, self::ListenNumber,
        ], true);
    }

    /**
     * A card that asks a CHOICE beside what it is mainly for: `dialogue_ask`, which carries the exchange's check since
     * наряд BACK-TAILS-1 §1.5. Its own result is the voice's; the choice is judged apart and is the only thing on it
     * that can lapse ({@see lapses()}).
     */
    public function hasChoice(): bool
    {
        return $this === self::DialogueAsk;
    }

    /**
     * A lapse is dealt once more at the end of its stage — except in «Слушаю и отвечаю»: its review (34-3) shows every
     * answer, so a copy after it would test what the learner has just been shown (SESSION-1a, хвост). There the first
     * failure is the only one. A card with a choice comes back like the choice card it swallowed.
     */
    public function requeues(): bool
    {
        return ($this->isChoice() && $this->stage() !== Stage::Listen) || $this->lapsesOnSkip() || $this->hasChoice();
    }

    /**
     * A phrase said aloud whose give-up is a lapse of its frame (SESSION-1d, DECISIONS п. 327): `phrase_repeat` and
     * `phrase_other_slot`. The other voice cards keep «two attempts without a pass — a skip, nothing more».
     */
    public function lapsesOnSkip(): bool
    {
        return $this === self::PhraseRepeat || $this === self::PhraseOtherSlot;
    }

    /**
     * Is this answer a LAPSE — the evidence that deals a copy and, the second time, returns the unit? A choice answered
     * wrong; a phrase said aloud given up on after two attempts ({@see SPOKEN_LAPSE_ATTEMPTS}) — not a skip before the
     * second attempt, and not a skip for want of a microphone (`no_mic`): the learner's will and a dead microphone
     * prove nothing (DECISIONS п. 327). Nothing else is.
     *
     * On a card that CARRIES a choice ({@see hasChoice()}) the lapse is the choice and nothing else: `dialogue_ask`
     * swallowed the check card of its exchange (наряд BACK-TAILS-1 §1.5), and the consequence the check card had —
     * a copy, then the exchange back tomorrow — went with it. Its voice result stays what the voice always was: two
     * attempts without coverage are a skip, and a skip is no lapse. A card dealt without its check, or answered
     * without a choice, lapses over nothing.
     *
     * @param  bool|null  $choiceRight  whether the option chosen is the right one; null when none was sent
     */
    public function lapses(CardResult $result, int $attempts, bool $noMic, ?bool $choiceRight = null): bool
    {
        return match (true) {
            $this->hasChoice() => $choiceRight === false,
            $this->isChoice() => $result === CardResult::Failed,
            $this->lapsesOnSkip() => $result === CardResult::Skipped && $attempts >= self::SPOKEN_LAPSE_ATTEMPTS && ! $noMic,
            default => false,
        };
    }

    /**
     * A card the learner SAYS and passes by coverage — two attempts without a pass are a skip, not a failure: the
     * recogniser's silence is not evidence of a lapse.
     */
    public function isSpoken(): bool
    {
        return in_array($this, [
            self::WordRepeat, self::PhraseRepeat, self::PhraseOtherSlot, self::DialogueAnswer, self::DialogueAsk,
            self::SpeakEcho, self::SpeakRetell,
        ], true);
    }

    /**
     * A slot said aloud and judged by meaning — the pass is the server's verdict (`…/judge`), never the client's word.
     * `speak_retell` is no longer among them (наряд BACK-TAILS-1 §1.1): it says the learner's own line back, and
     * coverage of that line is a thing the client counts itself.
     */
    public function isJudged(): bool
    {
        return $this === self::SpeakAnswer;
    }

    /**
     * A card that may ASK the judge — `speak_answer`, whose whole pass is the verdict, and «Скажи целиком»
     * (`phrase_other_slot`), whose LAST round is the learner's own value in the window (наряд FIX-2, п. 5).
     *
     * The two are not the same thing, and {@see isJudged()} is what tells them apart: on «Скажи целиком» the round
     * with one's own word is PRACTICE — «промах или „Пропустить" не создают копию и не возвращают единицу»
     * (решение архитектора 20.09) — so the verdict is recorded and shown, and the card's own result stays what its
     * value rounds made it, written by the client like any voice card's.
     */
    public function asksJudge(): bool
    {
        return $this === self::SpeakAnswer || $this === self::PhraseOtherSlot;
    }

    /** Read, heard or tapped through: it produces no answer, so it is walked or skipped, never right or wrong. */
    public function isWalkthrough(): bool
    {
        return in_array($this, [
            self::WordIntro, self::PhraseIntro, self::DialogueRescue, self::ListenDialogue, self::ListenReview, self::ListenPace,
            self::RecallScenes,
        ], true);
    }

    /**
     * What the client may write for this kind (D-31): a judged card only gives up (its pass is the judge's); a
     * walkthrough is walked or skipped; a spoken card is passed, passed with a hint or skipped — `failed` is not an
     * outcome of the voice; a choice takes all four.
     */
    public function allows(CardResult $result): bool
    {
        return match (true) {
            $this->isJudged() => $result === CardResult::Skipped,
            $this->isWalkthrough() => in_array($result, [CardResult::Passed, CardResult::Skipped], true),
            $this->isSpoken() => $result !== CardResult::Failed,
            default => true,
        };
    }

    /** @return list<self> every kind the assembler deals, in the registry's order */
    public static function dealt(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => $kind->isDealt()));
    }
}
