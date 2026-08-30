<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanStageFact;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;

/**
 * THE PLAN'S LADDER — which trainer a word of a plan owes next, derived from the review log alone.
 *
 * Three stages ({@see PlanStage}), a fixed list of trainers in each, and two rules about when you
 * move:
 *
 *   **To the next TRAINER — immediately on success.** The learner gets the word right in
 *   `multiple_choice`, the next card in the same sitting is the second `multiple_choice`, then the
 *   word bank, then speaking. A stage is meant to be walked through in one session.
 *
 *   **To the next STAGE — only in a session AFTER A NIGHT.** Closing stage A and starting stage B
 *   twenty minutes later would test nothing but short-term memory, which is the one thing a plan
 *   cannot afford to mistake for knowing. The night is counted in the learner's own local days
 *   ({@see PlanStageFact}), so «после ночи» means what a person means by it.
 *
 * ## Nothing is stored
 *
 * There is no `current_stage` column and there is not going to be one. The standing is a projection
 * over the append-only review log, exactly like every other statement this module makes about
 * progress, which is what makes it survive a replayed offline batch and what makes it testable
 * without a database. The cost is that this function walks a term's plan-relevant answers on every
 * read; the term counts are a day's worth of words, so that is a few dozen rows.
 *
 * ## A trainer this word cannot be dealt simply is not on its checklist
 *
 * `pick_correct` needs two validated wrong sentences; `dictation` needs an example of the right
 * length; `speaking` needs a microphone the learner has switched on. A word that cannot be dealt
 * one of them must not be stuck waiting for it forever — so the caller passes the modes that ARE
 * applicable and the checklist is drawn from those. The stage is closed when everything applicable
 * is closed, which is the only definition that cannot deadlock.
 *
 * ## Adaptation
 *
 * Three misses in a row inside a stage and the word is dealt on gentler knobs for the rest of that
 * stage ({@see \App\Modules\Learning\Domain\ValueObject\PlanKnobs::easier()}). Consecutive, so one
 * bad evening spread over three sessions does not trigger it; «до конца ступени», so it does not
 * flicker off on the next correct answer and leave the learner fighting the same word again.
 */
final class PlanStageLadder
{
    /**
     * The trainers of each stage, in the order they are dealt. The order is fixed and is not
     * configuration: it is the stage's meaning ({@see PlanStage}) written out.
     *
     * `multiple_choice` appears TWICE in stage A on purpose — forward and reverse recognition are
     * the same trainer asked in two directions, exactly as the ordinary ladder's rungs 1 and 2 are,
     * and a word recognised once has been recognised once.
     *
     * @var array<string, list<ExerciseMode>>
     */
    private const STEPS = [
        PlanStage::A->value => [
            ExerciseMode::Intro,
            ExerciseMode::MultipleChoice,
            ExerciseMode::MultipleChoice,
            ExerciseMode::WordBank,
            ExerciseMode::Speaking,
        ],
        PlanStage::B->value => [
            ExerciseMode::Cloze,
            ExerciseMode::Scramble,
            ExerciseMode::Listening,
            ExerciseMode::Speaking,
        ],
        PlanStage::C->value => [
            ExerciseMode::Typing,
            ExerciseMode::Dictation,
            ExerciseMode::PickCorrect,
            ExerciseMode::Speaking,
        ],
    ];

    /** Misses in a row that soften the knobs for the rest of the stage. */
    public const SOFTEN_AFTER_ERRORS = 3;

    /**
     * Every trainer this stage deals, applicable or not — for the admin screen and for the seed of
     * the plan-scoped knob rows, which need to know the set before any learner exists.
     *
     * @return list<ExerciseMode>
     */
    public static function modesOf(PlanStage $stage): array
    {
        return self::STEPS[$stage->value];
    }

    /**
     * Every trainer any stage deals, each once, in stage order.
     *
     * @return list<ExerciseMode>
     */
    public static function allModes(): array
    {
        $out = [];
        foreach (PlanStage::cases() as $stage) {
            foreach (self::modesOf($stage) as $mode) {
                if (! in_array($mode, $out, true)) {
                    $out[] = $mode;
                }
            }
        }

        return $out;
    }

    /**
     * WHICH RUNG OF THE ORDINARY LADDER a plan card is dealt at.
     *
     * The plan owns its own stages, but the CARD is the app's ordinary card and the assembler builds
     * it from a rung: the rung is what makes a `multiple_choice` forward or reverse, and what makes
     * `speaking` ask for the word or for the sentence ({@see ExerciseMode::gradesAgainstExample()}).
     * So the plan has to name one, and this is the translation table — in Domain, next to the stages
     * it translates, rather than as a `match` inside the session builder where nobody would find it.
     *
     * The three interesting rows:
     *
     *   the two stage-A recognitions   the first is FORWARD, the second REVERSE. That is what «×2»
     *                                  means — the same trainer asked in both directions, exactly as
     *                                  rungs 1 and 2 of the ordinary ladder are.
     *   speaking in stage A            the assembly rung, so the card asks for the WORD: translation
     *                                  on screen, say the term.
     *   speaking in stages B and C     the dictation rung, so the card asks for the EXAMPLE. Whether
     *                                  the sentence is on the screen is the stage's own answer
     *                                  ({@see PlanStage::speakingForm()}) and not a rung — the
     *                                  trainer is not asked to know about plans.
     *
     * @param  int  $occurrence  which appearance of this mode inside the stage, 1-based
     */
    public static function ladderStepFor(PlanStage $stage, ExerciseMode $mode, int $occurrence): int
    {
        if ($mode === ExerciseMode::Intro) {
            return LearningLadder::STEP_INTRO;
        }

        if ($stage === PlanStage::A) {
            return match (true) {
                $mode === ExerciseMode::MultipleChoice && $occurrence <= 1 => LearningLadder::STEP_RECOGNITION_FORWARD,
                $mode === ExerciseMode::MultipleChoice => LearningLadder::STEP_RECOGNITION_REVERSE,
                default => LearningLadder::STEP_ASSEMBLY,
            };
        }

        if ($stage === PlanStage::B) {
            // Everything in B works off a sentence that is on the screen; only speaking needs the
            // rung to say «ask for the example» rather than «ask for the word».
            return $mode === ExerciseMode::Speaking ? LearningLadder::STEP_DICTATION : LearningLadder::STEP_ASSEMBLY;
        }

        // Stage C takes the screen away: typed production and above.
        return LearningLadder::STEP_DICTATION;
    }

    /**
     * WHERE THIS WORD STANDS.
     *
     * @param  list<ExerciseMode>  $applicable  the trainers this word can be dealt right now —
     *                                          switched on for the learner AND buildable from this
     *                                          term's data. Everything else falls out of the
     *                                          checklist rather than blocking it.
     * @param  list<PlanStageFact>  $facts      this term's plan-relevant answers, oldest first
     * @param  bool  $introduced                the word has been SHOWN (a `term_exposures` row). The
     *                                          intro card is the one step that writes no review, so
     *                                          it is the one step closed by something other than a
     *                                          fact.
     * @param  string  $today                   the learner's local day, `Y-m-d`
     */
    public function standingFor(array $applicable, array $facts, bool $introduced, string $today): PlanTermStanding
    {
        $stage = PlanStage::first();
        $cursor = 0;

        while (true) {
            $steps = $this->stepsFor($stage, $applicable);
            $walk = $this->walk($steps, $facts, $cursor, $introduced && $stage === PlanStage::A);

            if (! $walk['complete']) {
                return new PlanTermStanding(
                    stage: $stage,
                    checklist: $walk['checklist'],
                    nextMode: $walk['next'],
                    stageComplete: false,
                    waitingForNight: false,
                    finished: false,
                    softened: $walk['softened'],
                );
            }

            $next = $stage->next();

            // A stage NOBODY can be dealt — every one of its trainers switched off, or none of them
            // buildable from this term — is passed through rather than waited on. It closed on no
            // day, so there is no night to wait for.
            $advanceable = $walk['empty'] || ($walk['closedOn'] !== null && $walk['closedOn'] < $today);

            // Closed, and the night has not passed. The word owes nothing today — which is a
            // different sentence from «this word is done» and the API says both.
            if (! $advanceable) {
                return new PlanTermStanding(
                    stage: $stage,
                    checklist: $walk['checklist'],
                    nextMode: null,
                    stageComplete: true,
                    waitingForNight: $next !== null,
                    finished: $next === null,
                    softened: $walk['softened'],
                );
            }

            if ($next === null) {
                // Stage C closed and its night passed: three stages lived. This is «готовность
                // слова» and the only thing that makes it true.
                return new PlanTermStanding(
                    stage: $stage,
                    checklist: $walk['checklist'],
                    nextMode: null,
                    stageComplete: true,
                    waitingForNight: false,
                    finished: true,
                    softened: false,
                );
            }

            $stage = $next;
            $cursor = $walk['cursor'];
        }
    }

    /**
     * The stage's trainers, narrowed to the ones this word can be dealt. Duplicates are kept — the
     * two recognition cards of stage A are two steps, not one.
     *
     * @param  list<ExerciseMode>  $applicable
     * @return list<ExerciseMode>
     */
    private function stepsFor(PlanStage $stage, array $applicable): array
    {
        return array_values(array_filter(
            self::modesOf($stage),
            static fn (ExerciseMode $mode): bool => in_array($mode, $applicable, true),
        ));
    }

    /**
     * Walk this stage's window of the log and see how far the checklist got.
     *
     * A correct answer closes the FIRST still-open step of its own mode — not «the next step
     * whatever it is», because the learner may also have met this word in an ordinary study session
     * on a trainer the plan is not waiting for, and that answer should count for the step it
     * actually was and for no other.
     *
     * @param  list<ExerciseMode>  $steps
     * @param  list<PlanStageFact>  $facts
     * @return array{checklist: list<array{mode: string, ordinal: int, done: bool}>, complete: bool, next: ExerciseMode|null, closedOn: string|null, cursor: int, softened: bool, empty: bool}
     */
    private function walk(array $steps, array $facts, int $cursor, bool $introClosed): array
    {
        /** @var list<bool> $done */
        $done = array_fill(0, count($steps), false);

        // The intro writes no review, so it is closed by the exposure the caller read.
        foreach ($steps as $i => $mode) {
            if ($mode === ExerciseMode::Intro && $introClosed) {
                $done[$i] = true;
            }
        }

        $remaining = count(array_filter($done, static fn (bool $d): bool => ! $d));
        $closedOn = null;
        $index = $cursor;
        $errorRun = 0;
        $softened = false;

        for (; $index < count($facts) && $remaining > 0; $index++) {
            $fact = $facts[$index];

            if (! $fact->correct) {
                $errorRun++;
                if ($errorRun >= self::SOFTEN_AFTER_ERRORS) {
                    $softened = true;
                }

                continue;
            }
            $errorRun = 0;

            foreach ($steps as $i => $mode) {
                if (! $done[$i] && $mode === $fact->mode) {
                    $done[$i] = true;
                    $remaining--;
                    if ($remaining === 0) {
                        $closedOn = $fact->localDate;
                    }

                    break;
                }
            }
        }

        $checklist = [];
        $next = null;
        foreach ($steps as $i => $mode) {
            $checklist[] = ['mode' => $mode->value, 'ordinal' => $i + 1, 'done' => $done[$i]];
            if (! $done[$i] && $next === null) {
                $next = $mode;
            }
        }

        return [
            'checklist' => $checklist,
            'complete' => $remaining === 0,
            'next' => $next,
            'closedOn' => $closedOn,
            // Where the NEXT stage starts reading. `$index` stopped on the fact after the closing
            // one, so a stage never re-counts an answer that already closed a step below it.
            'cursor' => $index,
            'softened' => $softened,
            'empty' => $steps === [],
        ];
    }
}
