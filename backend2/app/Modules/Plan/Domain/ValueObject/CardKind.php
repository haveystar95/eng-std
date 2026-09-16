<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * THE REGISTRY OF DAY TRAINERS (наряд SESSION-1a, разд. 1): twenty-nine kinds, twenty-eight of them dealt —
 * `listen_pairs` is reserved in the enum and never dealt (the lesson has no two alike lines to pair; its source is
 * v4.6).
 *
 * Every kind belongs to exactly one stage and to exactly one way of being counted, because the way it is counted is
 * what the server lets the client write:
 *
 * - CHOICE — a choice or an assembly the client grades without the network: right, right with a hint, wrong (the
 *   first wrong one comes back at the end of the stage, the second marks the unit to return), or given up on;
 * - SPOKEN — said aloud and passed by coverage: two attempts without a pass are a skip, not a failure — the
 *   recogniser's silence is no evidence of a lapse;
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
    case PhraseOwnSlot = 'phrase_own_slot';

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

    public function stage(): Stage
    {
        return match ($this) {
            self::WordIntro, self::WordRepeat, self::WordChoose, self::WordListen, self::WordAssemble, self::WordInLine => Stage::Words,
            self::PhraseIntro, self::PhraseAssemble, self::PhraseChooseBack, self::PhraseSlot, self::PhraseSlotListen,
            self::PhraseRepeat, self::PhraseOtherSlot, self::PhraseCombine, self::PhraseOwnSlot => Stage::Phrases,
            self::DialoguePartner, self::DialogueAnswer, self::DialogueAsk, self::DialogueRescue => Stage::Dialogue,
            self::ListenDialogue, self::ListenQuestion, self::ListenReview, self::ListenPairs, self::ListenPredict,
            self::ListenPace, self::ListenNumber => Stage::Listen,
            self::SpeakAnswer, self::SpeakEcho, self::SpeakRetell => Stage::Speak,
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
     * A card the learner SAYS and passes by coverage — two attempts without a pass are a skip, not a failure: the
     * recogniser's silence is not evidence of a lapse.
     */
    public function isSpoken(): bool
    {
        return in_array($this, [
            self::WordRepeat, self::PhraseRepeat, self::PhraseOtherSlot, self::DialogueAnswer, self::DialogueAsk, self::SpeakEcho,
        ], true);
    }

    /** A slot or a retelling judged by meaning — the pass is the server's verdict (`…/judge`), never the client's word. */
    public function isJudged(): bool
    {
        return in_array($this, [self::PhraseOwnSlot, self::SpeakAnswer, self::SpeakRetell], true);
    }

    /** Read, heard or tapped through: it produces no answer, so it is walked or skipped, never right or wrong. */
    public function isWalkthrough(): bool
    {
        return in_array($this, [
            self::WordIntro, self::PhraseIntro, self::DialogueRescue, self::ListenDialogue, self::ListenReview, self::ListenPace,
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

    /** Which card a unit comes back as on the next content day, after failing twice; a day unit never comes back. */
    public static function returnedFor(UnitKind $unit): ?self
    {
        return match ($unit) {
            UnitKind::Word => self::WordChoose,
            UnitKind::Phrase => self::PhraseSlot,
            UnitKind::Exchange => self::SpeakAnswer,
            UnitKind::Day => null,
        };
    }

    /** @return list<self> every kind the assembler deals, in the registry's order */
    public static function dealt(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $kind): bool => $kind->isDealt()));
    }
}
