<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\ContentAssessment;
use App\Modules\Learning\Domain\ValueObject\ContentGap;
use App\Modules\Learning\Domain\ValueObject\ContentStatus;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ModeContentVerdict;
use App\Modules\Learning\Domain\ValueObject\TermPlayability;
use LogicException;

/**
 * What a term's CONTENT allows each trainer to do — and, when it allows nothing, WHY.
 *
 * The sibling of {@see ModePassport}, and deliberately its opposite half. The passport is about the
 * LEARNER: the earliest rung at which a trainer's question can honestly be asked. This is about the
 * TERM: whether the card can be built at all out of the text, the example, the translation of that
 * example and the distractors hanging off it. A word can be ready by the passport and impossible by
 * the content (a single word at the dictation rung with no example), and the two failures need
 * different cures — one is the admission matrix, the other is the станок.
 *
 * IT INVENTS NO RULES. Every yes/no here comes from {@see TermPlayability::supports()} through
 * {@see PlayabilityAssessor}, which is the same derivation the live session assembler and the
 * day-plan simulator run — so a screen built on this cannot promise a card the trainer would refuse
 * to deal. What this adds is the REASON: `supports()` returns a bool because a session only needs to
 * know whether to deal the card, and a back-office screen needs to know what to do about it. The
 * reason is therefore re-derived from the same facts, in the same order as the clauses of
 * `supports()`, and the consistency test at `tests/Unit/Learning/ModeContentRequirementsTest.php`
 * pins the two together the way `ModePassportTest` pins the passport to the ladder.
 *
 * `multiple_choice` is the one mode this cannot answer with yes or no, and it says so
 * ({@see ContentStatus::PoolDependent}) instead of guessing. Its options are OTHER WORDS — the
 * session's own neighbours at the recognition rungs, the distractor reader's pool above them — so
 * whether its card assembles is a fact about the session it is dealt in. See
 * {@see \App\Modules\Learning\Application\Service\StudyCardAssembler}: an ordinary multiple_choice
 * card is refused when the pool cannot supply even one wrong option, and no content of this term
 * would have prevented that.
 */
final readonly class ModeContentRequirements
{
    public function __construct(
        private PlayabilityAssessor $playability,
        private DistractorSpanFilter $spans,
    ) {}

    /**
     * The same four inputs {@see PlayabilityAssessor::assess()} takes, except that distractors
     * arrive as their raw `error_span`s and are counted here — one derivation of «годный
     * дистрактор», shared with the card assembler ({@see DistractorSpanFilter}).
     *
     * @param  string       $answer              the term's own text (the card's answer)
     * @param  string|null  $example             the PINNED example sentence, if the term has one
     * @param  string|null  $exampleTranslation  that sentence in the learner's language
     * @param  list<string> $distractorSpans     `error_span` of every distractor on the pinned example
     */
    public function assess(
        string $answer,
        ?string $example,
        ?string $exampleTranslation,
        array $distractorSpans = [],
    ): ContentAssessment {
        $hasExample = $example !== null && $example !== '';
        // Distractors hang off the pinned example; with no example there is nothing they could hang
        // off, so they cannot stock the term on their own. Same rule as the assessor's.
        $usable = $hasExample ? $this->spans->countUsable($distractorSpans) : 0;
        $playable = $this->playability->assess($answer, $example, $exampleTranslation, $usable);

        $modes = [];
        foreach (ExerciseMode::cases() as $mode) {
            $modes[$mode->value] = $this->verdict($mode, $playable, $hasExample);
        }

        return new ContentAssessment($usable, $modes);
    }

    /**
     * Does this trainer build its wrong options out of OTHER terms? Written as an exhaustive match
     * rather than a list, so a new mode cannot be added without someone answering the question —
     * PHPStan points at the missing arm.
     */
    public static function isPoolDependent(ExerciseMode $mode): bool
    {
        return match ($mode) {
            // Its options ARE other words: the session's neighbours at rungs 1–2, the distractor
            // reader's pool at rung 3 and above.
            ExerciseMode::MultipleChoice => true,
            // pick_correct also shows wrong options, but they are this term's OWN distractors —
            // written by the станок against this term's own example. That is a content question.
            ExerciseMode::PickCorrect,
            ExerciseMode::WordBank,
            ExerciseMode::Cloze,
            ExerciseMode::Scramble,
            ExerciseMode::Typing,
            ExerciseMode::Listening,
            ExerciseMode::Dictation,
            ExerciseMode::Speaking,
            ExerciseMode::Intro => false,
        };
    }

    private function verdict(ExerciseMode $mode, TermPlayability $playable, bool $hasExample): ModeContentVerdict
    {
        if (self::isPoolDependent($mode)) {
            return new ModeContentVerdict(
                $mode,
                ContentStatus::PoolDependent,
                ContentGap::OptionsFromPool,
                'зависит от пула: неверные варианты берутся из ДРУГИХ слов сессии, а не из контента термина — контент термина здесь ничего не решает.',
            );
        }

        if ($playable->supports($mode)) {
            return new ModeContentVerdict($mode, ContentStatus::Ok, null, $this->okReason($mode, $playable));
        }

        $gap = $this->gapFor($mode, $playable, $hasExample);

        return new ModeContentVerdict($mode, ContentStatus::Blocked, $gap, $this->gapReason($gap, $playable));
    }

    /**
     * Which clause of {@see TermPlayability::supports()} refused this mode. The order of the checks
     * mirrors the order of the clauses there — the first unmet condition is the one reported, so
     * "нет примера" is never dressed up as "пример слишком короткий" (a missing example tokenizes
     * to 0 chips, which would satisfy the length branch and say nothing useful).
     */
    private function gapFor(ExerciseMode $mode, TermPlayability $playable, bool $hasExample): ContentGap
    {
        return match ($mode) {
            ExerciseMode::WordBank => ContentGap::SingleWord,
            ExerciseMode::Cloze => $hasExample ? ContentGap::ExampleLacksTerm : ContentGap::NoExample,
            ExerciseMode::Scramble => match (true) {
                ! $hasExample => ContentGap::NoExample,
                $playable->exampleIsAnswer => ContentGap::ExampleIsTerm,
                ! $playable->hasExampleTranslation => ContentGap::NoExampleTranslation,
                $playable->exampleTokenCount < TermPlayability::MIN_SCRAMBLE_TOKENS => ContentGap::ExampleTooShort,
                default => ContentGap::ExampleTooLong,
            },
            ExerciseMode::Dictation => match (true) {
                ! $hasExample => ContentGap::NoExample,
                $playable->exampleIsAnswer => ContentGap::ExampleIsTerm,
                $playable->exampleTokenCount < TermPlayability::MIN_DICTATION_TOKENS => ContentGap::ExampleTooShort,
                default => ContentGap::ExampleTooLong,
            },
            ExerciseMode::PickCorrect => match (true) {
                ! $hasExample => ContentGap::NoExample,
                $playable->exampleIsAnswer => ContentGap::ExampleIsTerm,
                ! $playable->hasExampleTranslation => ContentGap::NoExampleTranslation,
                default => ContentGap::TooFewDistractors,
            },
            // These four fit EVERY term — they ask for the term itself, or (intro) for nothing at
            // all — so `supports()` never refuses them and this arm is unreachable. A throw rather
            // than a placeholder: a gap invented for a mode that has none would be printed on a
            // screen and sent to the станок.
            ExerciseMode::Typing, ExerciseMode::Listening, ExerciseMode::Speaking,
            ExerciseMode::Intro, ExerciseMode::MultipleChoice => throw new LogicException(
                "{$mode->value} подходит любому термину — у него не может быть контентного отказа.",
            ),
        };
    }

    private function gapReason(ContentGap $gap, TermPlayability $playable): string
    {
        return match ($gap) {
            ContentGap::SingleWord => 'ответ — одно слово: сборка из одной фишки ничего не спрашивает (нужно минимум '
                . TermPlayability::MIN_WORD_BANK_WORDS . ' слова).',
            ContentGap::NoExample => 'у термина нет закреплённого примера — вырезать пропуск, перемешать или продиктовать нечего.',
            ContentGap::ExampleLacksTerm => 'пример не содержит сам термин, поэтому пропуск вырезать не из чего.',
            ContentGap::ExampleIsTerm => 'пример совпадает с самим термином — это была бы та же карточка, что и сборка слова.',
            ContentGap::NoExampleTranslation => 'у примера нет перевода, а именно перевод и есть вопрос карточки.',
            ContentGap::ExampleTooShort => "в примере {$playable->exampleTokenCount} слов(а) — слишком короткий для этого тренажёра.",
            ContentGap::ExampleTooLong => "в примере {$playable->exampleTokenCount} слов — длиннее потолка этого тренажёра.",
            ContentGap::TooFewDistractors => "годных дистракторов {$playable->distractorCount}, а карточке нужно минимум "
                . TermPlayability::MIN_PICK_CORRECT_DISTRACTORS . ' (эталон + 2 неверных предложения).',
            ContentGap::OptionsFromPool => 'варианты берутся из других слов пула, а не из контента термина.',
        };
    }

    private function okReason(ExerciseMode $mode, TermPlayability $playable): string
    {
        return match ($mode) {
            ExerciseMode::Intro => 'карточка только показывает слово — контент для неё не нужен.',
            ExerciseMode::Typing, ExerciseMode::Listening => 'спрашивает сам термин — подходит любому термину.',
            ExerciseMode::Speaking => $playable->exampleIsAnswer || $playable->exampleTokenCount === 0
                ? 'спрашивает сам термин вслух; без примера остаётся словесная форма — это деградация внутри режима, а не отказ.'
                : 'спрашивает сам термин вслух; на верхней ступени читается пример.',
            ExerciseMode::WordBank => "ответ из {$playable->answerWordCount} слов — есть что собирать из фишек.",
            ExerciseMode::Cloze => 'пример содержит термин — есть откуда вырезать пропуск.',
            ExerciseMode::Scramble => "пример на {$playable->exampleTokenCount} слов с переводом — есть что собирать.",
            ExerciseMode::Dictation => "пример на {$playable->exampleTokenCount} слов — есть что диктовать.",
            ExerciseMode::PickCorrect => "годных дистракторов {$playable->distractorCount} — хватает на эталон + 2 неверных.",
            ExerciseMode::MultipleChoice => 'подходит любому термину, но опции берутся из пула.',
        };
    }
}
