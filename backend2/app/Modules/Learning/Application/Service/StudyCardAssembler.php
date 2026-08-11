<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Dto\SessionCardView;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Random\Randomizer;

/**
 * Turns a due term + its content into a playable card: the ExerciseSelector picks the mode from
 * the term's state, then the mode decides the extras — shuffled options (answer + distractors)
 * for multiple_choice, shuffled chips for word_bank, nothing for typing. The prompt is the
 * translation (user's language); the answer is the target text.
 */
final readonly class StudyCardAssembler
{
    private const OPTION_COUNT = 4;

    /** Vocabulary term-type string (kept as a literal — Learning must not import Vocabulary Domain). */
    private const PHRASAL_VERB = 'phrasal_verb';

    public function __construct(
        private ExerciseSelector $selector,
        private PlayabilityAssessor $playability,
        private DistractorReader $distractors,
        private ChipShuffler $chips,
        private Randomizer $rng,
    ) {}

    /** @param list<string> $poolTermIds */
    public function assemble(
        UserId $user,
        DueTermView $view,
        TermContentView $content,
        array $poolTermIds,
        EnabledModes $enabled,
        bool $isPractice = false,
        int $cardIndex = 0,
    ): SessionCardView {
        $progress = TermProgress::reconstitute(
            $user, $view->termId, $view->state, TermProgress::DEFAULT_EASE,
            $view->intervalDays, $view->dueAt, $view->reps, 0, null,
        );
        $answer = $content->text;
        // What the term's data allows at all — one derivation, shared with the day-plan simulator.
        $playable = $this->playability->assess($answer, $content->example);
        // Practice fans across every applicable mode (round-robin by card + a per-term offset), so a
        // session shows them all and repeats re-deal; SRS keeps the reps ladder.
        $mode = $isPractice
            ? $this->selector->selectForPractice($enabled, $cardIndex + $this->termOffset($view->termId->value), $playable)
            : $this->selector->select($progress, $enabled, $playable);

        $options = null;
        $chips = null;

        if ($mode === ExerciseMode::MultipleChoice) {
            $distractors = $this->distractors->forTarget($view->termId, $poolTermIds, self::OPTION_COUNT - 1);
            /** @var list<string> $options */
            $options = $this->rng->shuffleArray([$answer, ...$distractors]);
        } elseif ($mode === ExerciseMode::WordBank) {
            $chips = $this->chips->chips($answer, $content->type === self::PHRASAL_VERB);
        }

        return new SessionCardView(
            termId: $view->termId->value,
            exerciseMode: $mode->value,
            type: $content->type,
            prompt: $content->translation,
            answer: $answer,
            transcription: $content->transcription,
            example: $content->example,
            exampleTranslation: $content->exampleTranslation,
            options: $options,
            chips: $chips,
        );
    }

    /**
     * A stable, well-spread offset per term so the practice round-robin doesn't hand the same mode
     * to every card at a given index — deterministic (crc32), so it's testable and reproducible.
     */
    private function termOffset(string $termId): int
    {
        return (int) crc32($termId);
    }
}
