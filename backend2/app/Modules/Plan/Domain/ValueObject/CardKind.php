<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * The thirteen cards a day can be made of. Each belongs to exactly one stage, and each knows
 * whether it is graded at all — an introduction is read and passed, never answered.
 */
enum CardKind: string
{
    case WordIntro = 'word_intro';
    case WordSay = 'word_say';
    case WordChoose = 'word_choose';
    case WordCloze = 'word_cloze';
    case PhraseIntro = 'phrase_intro';
    case PhraseRepeat = 'phrase_repeat';
    case PhraseAssemble = 'phrase_assemble';
    case DialogueRead = 'dialogue_read';
    case ListenQuestion = 'listen_question';
    case ListenAssemble = 'listen_assemble';
    case AnswerChoose = 'answer_choose';
    case AnswerAssemble = 'answer_assemble';
    case Speak = 'speak';

    public function stage(): Stage
    {
        return match ($this) {
            self::WordIntro, self::WordSay, self::WordChoose, self::WordCloze => Stage::Words,
            self::PhraseIntro, self::PhraseRepeat, self::PhraseAssemble => Stage::Phrases,
            self::DialogueRead => Stage::Dialogue,
            self::ListenQuestion, self::ListenAssemble, self::AnswerChoose, self::AnswerAssemble => Stage::Listen,
            self::Speak => Stage::Speak,
        };
    }

    /** An introduction produces no answer: it counts as walked, never as right or wrong. */
    public function isGraded(): bool
    {
        return ! in_array($this, [self::WordIntro, self::PhraseIntro, self::DialogueRead], true);
    }

    /**
     * A card the learner SAYS — two attempts without a pass are a skip, not a failure: the
     * recogniser's silence is not evidence of a lapse.
     */
    public function isSpoken(): bool
    {
        return in_array($this, [self::WordSay, self::PhraseRepeat, self::Speak], true);
    }

    /** Which card a unit comes back as on the next content day, after failing twice. */
    public static function returnedFor(UnitKind $unit): self
    {
        return match ($unit) {
            UnitKind::Word => self::WordChoose,
            UnitKind::Phrase => self::PhraseAssemble,
            UnitKind::Exchange => self::Speak,
        };
    }
}
