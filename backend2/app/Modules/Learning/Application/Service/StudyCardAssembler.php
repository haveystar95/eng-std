<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\ModeFallbackReporter;
use App\Modules\Learning\Application\Dto\SessionCardView;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Service\ChipShuffler;
use App\Modules\Learning\Domain\Service\DistractorSpanFilter;
use App\Modules\Learning\Domain\Service\ExerciseSelector;
use App\Modules\Learning\Domain\Service\PlayabilityAssessor;
use App\Modules\Learning\Domain\Service\LearningLadder;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Learning\Domain\ValueObject\OptionsPolicy;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Random\Randomizer;

/**
 * Turns a selected term + its content into a playable card: the ExerciseSelector picks the mode
 * from the term's rung on the acquisition ladder, then the mode decides the extras — shuffled
 * options (answer + distractors) for multiple_choice, shuffled chips for word_bank, nothing for
 * typing. The prompt is the translation (user's language); the answer is the target text.
 *
 * The two lowest rungs are the exceptions, and both are handled here rather than by the mode:
 *
 *  * rung 0, `intro` — the word is shown, not asked. No options, no answer to give.
 *  * rungs 1–2, recognition — the options are the session's OWN NEIGHBOURS, not the enrichment
 *    distractors. The distractors exist to be nearly right, which is the whole point of them at
 *    rung 3 and above; at a first meeting they turn "do you remember what this means" into a
 *    spelling test the learner has no basis to pass. Far options make the card answerable by
 *    knowing the word and by nothing else.
 */
final readonly class StudyCardAssembler
{
    private const OPTION_COUNT = 4;

    /**
     * Below this, a choice card is not a card. One option is not a question — the learner taps the
     * only thing on screen and the log records a correct answer nobody gave. {@see assemble()}.
     */
    private const MIN_OPTIONS = 2;

    /**
     * `pick_correct` shows three sentences: the example plus two wrong ones. Three rather than
     * multiple_choice's four because each option is a whole sentence to read — a fourth turns the card
     * into a reading comprehension test.
     */
    private const PICK_CORRECT_WRONG_OPTIONS = 2;

    /** Vocabulary term-type string (kept as a literal — Learning must not import Vocabulary Domain). */
    private const PHRASAL_VERB = 'phrasal_verb';

    public function __construct(
        private ExerciseSelector $selector,
        private PlayabilityAssessor $playability,
        private ModeFallbackReporter $fallbacks,
        private DistractorReader $distractors,
        private ChipShuffler $chips,
        private Randomizer $rng,
        private DistractorSpanFilter $spans = new DistractorSpanFilter(),
    ) {}

    /**
     * @param  list<string>  $poolTermIds
     * @param  list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>  $neighbours
     *         the other terms in THIS session — the far-option pool for the recognition rungs
     * @param  int|null  $slotStep  the rung this particular card was laid out at; a session gives a
     *                              term up to three cards at different rungs ({@see SessionLayout})
     * @param  ExerciseMode|null  $modeOverride  the mode this slot must use, already chosen from the
     *                              same three filters — the single-term practice FAN, which deals one
     *                              card per applicable mode instead of one card off the round-robin
     *                              ({@see BuildStudySessionHandler::practiceSlots()}). Null otherwise.
     */
    public function assemble(
        UserId $user,
        DueTermView $view,
        TermContentView $content,
        array $poolTermIds,
        EnabledModes $enabled,
        ModeAdmission $admission,
        bool $isPractice = false,
        int $cardIndex = 0,
        ?int $slotStep = null,
        array $neighbours = [],
        ?ExerciseMode $modeOverride = null,
    ): ?SessionCardView {
        $progress = TermProgress::reconstitute(
            $user, $view->termId, $view->state, TermProgress::DEFAULT_EASE,
            $view->intervalDays, $view->dueAt, $view->reps, 0, null,
            acquisition: $view->acquisition, learningStep: $view->learningStep,
            successfulReviews: $view->successfulReviews,
        );
        $answer = $content->text;
        // Span-distinct, because that is what a card can actually use — see spanDistinct().
        $usableDistractors = $this->spanDistinct($content->exampleDistractors);
        // What the term's data allows at all — one derivation, shared with the day-plan simulator.
        $playable = $this->playability->assess(
            $answer,
            $content->example,
            $content->exampleTranslation,
            count($usableDistractors),
        );
        // Toggles are per-user data, so "nothing fits this term" is now reachable by configuration.
        // The selector still returns a playable card; this is what stops that being silent.
        if (! $this->selector->hasApplicableMode($enabled, $playable)) {
            $this->fallbacks->noApplicableMode(
                $user,
                $view->termId,
                array_map(static fn (ExerciseMode $m): string => $m->value, $enabled->modes),
            );
        }
        // Practice fans across every applicable mode (round-robin by card + a per-term offset), so a
        // session shows them all and repeats re-deal; SRS keeps the reps ladder. A one-term practice
        // session fans WITHIN itself instead, and hands each slot its mode outright.
        $mode = $modeOverride
            ?? ($isPractice
                ? $this->selector->selectForPractice(
                    $this->practiceModes($view, $enabled, $admission),
                    $cardIndex + $this->termOffset($view->termId->value),
                    $playable,
                )
                : $this->selector->select($progress, $enabled, $playable, $admission, $slotStep));

        // The rung this card is actually being dealt at. Practice is off the ladder entirely — it
        // schedules nothing and advances nothing — so it carries no rung.
        $step = $isPractice ? null : $this->selector->effectiveStep($progress, $enabled, $admission, $slotStep);

        if ($mode === ExerciseMode::Intro) {
            return $this->introCard($view, $content);
        }
        // Where the wrong options come from is POLICY, read from the matrix, not inferred from the
        // rung: `distant` (the session's own neighbours) is what makes a first meeting winnable,
        // and it is stored per mode so it can be moved without a deploy.
        if (LearningLadder::isRecognitionStep($step)
            && $mode === ExerciseMode::MultipleChoice
            && $admission->optionsPolicyFor($mode, $view->acquisition) === OptionsPolicy::Distant) {
            $card = $this->recognitionCard($view, $content, (int) $step, $neighbours, $cardIndex);
            if ($card !== null) {
                return $card;
            }
            // No neighbour of the same shape with the right side translated (a one-term session, a
            // deck of a single shape, or one whose translations have not landed yet). Fall through
            // to the ordinary multiple_choice below rather than deal a one-option card: near options
            // at a first meeting are a worse card, an unanswerable one is not a card at all.
        }

        $options = null;
        $chips = null;
        /** @var list<array{sentence: string, error_span: string, correction: string}>|null $optionFeedback */
        $optionFeedback = null;
        // A sentence-level mode asks for the EXAMPLE, so the card's answer (what the client checks
        // against and what the server grades) is that sentence, and the prompt is its translation —
        // "assemble this in English". The term's own translation would be the wrong question here.
        $prompt = $content->translation;
        // Does THIS card ask for the example — the mode's question at this rung, AND a term that
        // actually has the sentence to ask for. Read once, so the answer, the prompt and the
        // accepted variants below cannot disagree about what was asked. The content half never
        // fires for scramble/dictation/pick_correct (their playability gates guarantee an example);
        // it exists for `speaking`, whose word form is offered to exampleless terms on purpose.
        $asksExample = $mode->gradesAgainstExample($step)
            && $content->example !== null
            && trim($content->example) !== '';

        if ($mode === ExerciseMode::MultipleChoice) {
            $distractors = $this->distractors->forTarget($view->termId, $poolTermIds, self::OPTION_COUNT - 1);
            /** @var list<string> $options */
            $options = $this->rng->shuffleArray([$answer, ...$distractors]);
        } elseif ($mode === ExerciseMode::WordBank) {
            $chips = $this->chips->chips($answer, $content->type === self::PHRASAL_VERB);
        } elseif ($mode === ExerciseMode::Scramble) {
            // The selector only reaches scramble when the gate passed, so the example is present.
            $answer = (string) $content->example;
            $prompt = $content->exampleTranslation;
            $chips = $this->chips->sentenceChips($answer);
        } elseif ($mode === ExerciseMode::PickCorrect) {
            // Three sentences, one right. The answer is the example verbatim (the mode grades against
            // the example), the prompt is its translation — "which of these says this correctly?".
            $answer = (string) $content->example;
            $prompt = $content->exampleTranslation;
            $wrong = array_slice($usableDistractors, 0, self::PICK_CORRECT_WRONG_OPTIONS);
            /** @var list<string> $options */
            $options = $this->rng->shuffleArray([
                $answer,
                ...array_map(static fn (array $d): string => $d['sentence'], $wrong),
            ]);
            // Why the feedback rides on the card: the whole point of this mode over multiple_choice is
            // that a wrong pick can be EXPLAINED — underline the broken fragment, show what it should
            // have been. Sending it with the card keeps that working offline, and the client never has
            // to ask which part of the sentence was wrong.
            $optionFeedback = array_map(
                static fn (array $d): array => [
                    'sentence' => $d['sentence'],
                    'error_span' => $d['error_span'],
                    'correction' => $d['correction'],
                ],
                $wrong,
            );
        } elseif ($mode === ExerciseMode::Speaking) {
            // Two forms, one mode, chosen by the rung the card is dealt at — the same shape as the
            // recognition card's two directions ({@see ExerciseMode::gradesAgainstExample}).
            //
            //   word form     the prompt is the translation and the photo the client already has;
            //                 the learner says the TERM. Nothing else is on screen — printing the
            //                 term would turn free recall into reading aloud.
            //   example form  the pinned example IS the task: it is shown and read aloud, so the
            //                 card's answer is that sentence and the prompt is its translation.
            //
            // `asksExample` is computed rather than re-asked below, because a term with no example
            // has to degrade to the word form CONSISTENTLY — the same decision must reach the
            // answer, the prompt and the accepted variants, or the card grades against a sentence
            // it never showed.
            if ($asksExample) {
                $answer = (string) $content->example;
                $prompt = $content->exampleTranslation;
            }
        } elseif ($mode === ExerciseMode::Dictation) {
            // The task is the AUDIO. No written prompt at all — a translation on screen would turn
            // "write what you hear" into "translate this", which is a different exercise and an
            // easier one. The gate guarantees the example is there to be spoken.
            $answer = (string) $content->example;
            $prompt = null;
        }

        // THE OPTION FLOOR (QA-15), deliberately here — after every fallback branch above, not
        // inside one of them.
        //
        // Each branch that builds options has its own reason to come up short, and each was covered
        // in isolation: the recognition card returns null when it cannot find same-shape neighbours,
        // and pick_correct is gated on the term having distractors. What nobody covered was where
        // those fallbacks LAND — the ordinary multiple_choice below, which asks the distractor
        // reader for three wrong answers and takes however many it gets. For a phrase whose only
        // same-language neighbours all share its translation, that is none, and the learner was
        // shown «Как вы справляетесь с конфликтами?» above a single option: the answer.
        //
        // So the floor sits at the one place every path passes through, and it is a REFUSAL rather
        // than a repair. There is nothing honest to repair it with — padding the card means inventing
        // options, and dropping to another mode means asking a question this term's data cannot
        // support either. A missing card costs the learner one repetition; a card with one option
        // writes a correct answer into an append-only log for a retrieval that never happened.
        if ($options !== null && count($options) < self::MIN_OPTIONS) {
            // Refused, and REPORTED: a session that is quietly one card shorter is the kind of thing
            // nobody notices for months, and the cause here is the term's content — no neighbour, no
            // distractor — which no toggle the learner owns can fix.
            $this->fallbacks->tooFewOptions($user, $view->termId, $mode->value, count($options));

            return null;
        }

        return new SessionCardView(
            termId: $view->termId->value,
            exerciseMode: $mode->value,
            type: $content->type,
            prompt: $prompt,
            answer: $answer,
            transcription: $content->transcription,
            example: $content->example,
            exampleTranslation: $content->exampleTranslation,
            options: $options,
            chips: $chips,
            optionFeedback: $optionFeedback,
            // Only when this card's answer IS the term. On scramble/dictation the answer is the
            // example sentence, and the server's own expected set is that sentence alone — sending
            // the term's variants there would make the client accept what the server rejects.
            acceptedVariants: $asksExample ? [] : $content->acceptedVariants,
            ladderStep: $step,
        );
    }

    /**
     * The trainers free practice may pick from for this pair.
     *
     * For a POOL pair: the switched-on set, unfiltered — the ladder does not narrow free practice,
     * which is what {@see ExerciseSelector::selectForPractice()} has always done and what keeps
     * dictation reachable on a real pool (QA-26).
     *
     * For a pair OUTSIDE the pool — a word of the collection nobody has taken into study, which a
     * collection's practice now reaches — the matrix DOES narrow it, to what it opens at
     * {@see LearningLadder::STEP_UNENROLLED_PRACTICE}: choice and assembly, never typed production
     * or dictation. Such a word has no rung to have earned them with.
     *
     * The narrowing is applied to the ENABLED SET rather than inside the selector on purpose: the
     * round-robin, its rotation seed and its floor stay exactly as they are, and this stays one
     * filter over one list.
     *
     * An empty narrowing falls back to multiple_choice alone — {@see ExerciseSelector::floor()}'s
     * rule («the only mode that fits every term»), applied here because EnabledModes may not be
     * empty. Reachable only by switching off everything the assembly rung opens.
     */
    private function practiceModes(DueTermView $view, EnabledModes $enabled, ModeAdmission $admission): EnabledModes
    {
        if ($view->inPool) {
            return $enabled;
        }

        $opened = $admission->only($enabled->modes, LearningLadder::STEP_UNENROLLED_PRACTICE);

        return new EnabledModes($opened !== [] ? $opened : [ExerciseMode::MultipleChoice]);
    }

    /**
     * Every mode this pair may be drilled in RIGHT NOW, in the matrix's own order — the fan a
     * one-term practice session deals ({@see BuildStudySessionHandler::practiceSlots()}).
     *
     * The three filters, in the usual order and each doing exactly its own job: switched on
     * ({@see EnabledModes}, whose order is the `position` column and therefore the product's own),
     * admitted at this pair's rung ({@see ModeAdmission}), and buildable from this term's data
     * ({@see PlayabilityAssessor}). `intro` never appears — it is not a trainer and practice
     * introduces nothing.
     *
     * An empty result is «no mode applies», which is the caller's cue to deal one card off the
     * selector's floor rather than none.
     *
     * @return list<ExerciseMode>
     */
    public function practiceModesFor(
        DueTermView $view,
        TermContentView $content,
        EnabledModes $enabled,
        ModeAdmission $admission,
    ): array {
        $playable = $this->playability->assess(
            $content->text,
            $content->example,
            $content->exampleTranslation,
            count($this->spanDistinct($content->exampleDistractors)),
        );
        // A `known` pair is OUTSIDE the ladder, not at the bottom of it — its verification is decided
        // elsewhere — so it reads as the top rung rather than as rung 0. Same rule, same words, as
        // the client's `LadderPosition.admissionStep`. A pair outside the POOL reads as the fixed
        // rung a catalogue word is drilled at — it has no rung of its own to read.
        $step = $view->inPool
            ? (LearningLadder::stepFor(
                $view->acquisition,
                $view->reps,
                $view->learningStep,
                isKnown: $view->state === LearningState::Known,
            ) ?? LearningLadder::STEP_DICTATION)
            : LearningLadder::STEP_UNENROLLED_PRACTICE;
        $graded = array_values(array_filter($enabled->modes, static fn (ExerciseMode $m): bool => $m->isGraded()));

        return $playable->only($admission->only($graded, $step));
    }

    /**
     * Rung 0. The word is SHOWN: term, transcription, translation, example. Nothing is asked, so
     * there is no answer to check and no accepted variants to send — the client's only job is to
     * display it and report that it did, which becomes a `term_exposures` row.
     *
     * `answer` still carries the term text because the client renders the card from it like any
     * other; it is never compared against anything.
     */
    private function introCard(DueTermView $view, TermContentView $content): SessionCardView
    {
        return new SessionCardView(
            termId: $view->termId->value,
            exerciseMode: ExerciseMode::Intro->value,
            type: $content->type,
            prompt: $content->translation,
            answer: $content->text,
            transcription: $content->transcription,
            example: $content->example,
            exampleTranslation: $content->exampleTranslation,
            options: null,
            chips: null,
            acceptedVariants: [],
            ladderStep: LearningLadder::STEP_INTRO,
        );
    }

    /**
     * Rungs 1–2: four options, all of them the session's own neighbours, deliberately far.
     *
     *  * rung 1, `term → translation` — the prompt is the TERM and the options are translations.
     *    This is the one card in the app whose correct option is a translation, and it is graded by
     *    IDENTITY, not as text: `answer` is the card's own term id and `optionIds` says which term
     *    each option belongs to, so the learner taps and the client uploads an id. That keeps the
     *    answer-key rule («no translation ever in a text key») literally true.
     *  * rung 2, `translation → term` — the ordinary direction, graded as text against the term's
     *    own forms exactly as multiple_choice always has been.
     *
     * The options are all of the SAME TERM TYPE as the card's own term, and the card gets FEWER of
     * them rather than mixed ones. A far option must be far in meaning, not in shape: offering
     * «Где я могу найти корм для собак?» and «Подходит ли это для мелких пород?» beside «без злаков»
     * for the word `grain-free` lets the learner discard two options without reading them, turning a
     * one-in-four card into a coin toss (QA-6, приёмка 17.08). A generated collection mixes words
     * and sentences by design, so this happens constantly rather than occasionally. Three same-shape
     * options are the ideal; two are worse and one is worse still, but every one of them asks the
     * question this card exists to ask, which a padded four does not.
     *
     * Returns null when even ONE same-type option cannot be found: a one-term session, a deck of
     * one shape, or one whose translations have not been generated yet. A single-option card is not
     * a card, and the caller falls through to ordinary multiple_choice.
     *
     * @param  list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>  $neighbours
     */
    private function recognitionCard(
        DueTermView $view,
        TermContentView $content,
        int $step,
        array $neighbours,
        int $cardIndex,
    ): ?SessionCardView {
        $forward = $step === LearningLadder::STEP_RECOGNITION_FORWARD;

        $own = $forward ? $content->translation : $content->text;
        if ($own === null || trim($own) === '') {
            return null;
        }

        // Deterministic: rotate the neighbour list by this card's index so two cards in one session
        // do not get the same three options, and a rebuilt session deals the same ones. Then the
        // topic preference re-orders that rotation — see sameTopicFirst().
        $pool = [];
        foreach ($this->sameTopicFirst($this->rotate($neighbours, $cardIndex), $this->collectionsOf($neighbours, $view->termId->value)) as $neighbour) {
            if ($neighbour['term_id'] === $view->termId->value) {
                continue;
            }
            // Same shape or not at all. A neighbour of another type is skipped, never taken as a
            // filler — see the docblock: the card is allowed to be shorter, not to be guessable.
            if ($neighbour['type'] !== $content->type) {
                continue;
            }
            $text = $forward ? $neighbour['translation'] : $neighbour['text'];
            if ($text === null || trim($text) === '' || $this->sameOption($text, $own)) {
                continue;
            }
            $pool[] = ['term_id' => $neighbour['term_id'], 'text' => $text];
            if (count($pool) >= self::OPTION_COUNT - 1) {
                break;
            }
        }

        if ($pool === []) {
            return null;
        }

        // Shuffle the (id, text) PAIRS, never the two lists separately: the client finds the correct
        // option by id, and a misaligned pair would mark the right answer wrong.
        /** @var list<array{term_id: string, text: string}> $optionPairs */
        $optionPairs = $this->rng->shuffleArray([
            ['term_id' => $view->termId->value, 'text' => $own],
            ...$pool,
        ]);

        return new SessionCardView(
            termId: $view->termId->value,
            exerciseMode: ExerciseMode::MultipleChoice->value,
            type: $content->type,
            prompt: $forward ? $content->text : $content->translation,
            // Forward: the id IS the answer (identity grading). Reverse: the term's text, checked
            // as text like every other multiple_choice card.
            answer: $forward ? $view->termId->value : $content->text,
            transcription: $content->transcription,
            example: $content->example,
            exampleTranslation: $content->exampleTranslation,
            options: array_map(static fn (array $o): string => $o['text'], $optionPairs),
            chips: null,
            // Only meaningful where the answer is the term's own text.
            acceptedVariants: $forward ? [] : $content->acceptedVariants,
            ladderStep: $step,
            optionIds: $forward
                ? array_map(static fn (array $o): string => $o['term_id'], $optionPairs)
                : null,
        );
    }

    /**
     * Neighbours from the card's OWN topic first, everything else after, each half keeping the order
     * it arrived in (so the rotation above still varies the options card to card).
     *
     * A session used to be one collection, which made every neighbour a same-topic neighbour for
     * free. A pool session mixes topics, and a far option from another topic is far in the wrong
     * way: shown «без злаков», «пересадка» and «жаропонижающее» beside «выписка со счёта», the
     * learner picks the banking-shaped word without knowing it. Same-topic options are the ones that
     * make the card ask about the word.
     *
     * A preference and not a filter, deliberately: a topic with too few same-shape neighbours must
     * fall back to distant ones rather than lose the card, which is the same rule the type filter
     * follows one level down.
     *
     * @param  list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>  $neighbours
     * @param  list<string>  $own  the card term's own collections
     * @return list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>
     */
    private function sameTopicFirst(array $neighbours, array $own): array
    {
        if ($own === []) {
            return $neighbours;
        }

        $ownSet = array_flip($own);
        $same = [];
        $rest = [];
        foreach ($neighbours as $neighbour) {
            $shared = false;
            foreach ($neighbour['collections'] ?? [] as $collectionId) {
                if (isset($ownSet[$collectionId])) {
                    $shared = true;

                    break;
                }
            }
            if ($shared) {
                $same[] = $neighbour;
            } else {
                $rest[] = $neighbour;
            }
        }

        return array_merge($same, $rest);
    }

    /**
     * @param  list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>  $neighbours
     * @return list<string>
     */
    private function collectionsOf(array $neighbours, string $termId): array
    {
        foreach ($neighbours as $neighbour) {
            if ($neighbour['term_id'] === $termId) {
                return $neighbour['collections'] ?? [];
            }
        }

        return [];
    }

    /**
     * @param  list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>  $neighbours
     * @return list<array{term_id: string, text: string, translation: string|null, type: string, collections?: list<string>}>
     */
    private function rotate(array $neighbours, int $by): array
    {
        $count = count($neighbours);
        if ($count === 0) {
            return [];
        }
        $offset = (($by % $count) + $count) % $count;

        return array_merge(array_slice($neighbours, $offset), array_slice($neighbours, 0, $offset));
    }

    /**
     * Two options that read the same are one option. Synonymous neighbours are common in a
     * generated collection ("hi" / "hello"), and a card offering both has no correct answer.
     */
    private function sameOption(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /**
     * The distractors this example can actually contribute to a card: one per `error_span`.
     *
     * The rule itself lives in {@see DistractorSpanFilter} — it is read by the playability gate here
     * AND by the back-office content report, which has to count the same «годные» distractors the
     * card does. This wrapper stays so the two call sites below read the way they always have.
     *
     * @param  list<array{sentence: string, error_type: string, error_span: string, correction: string}>  $distractors
     * @return list<array{sentence: string, error_type: string, error_span: string, correction: string}>
     */
    private function spanDistinct(array $distractors): array
    {
        return $this->spans->usable($distractors);
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
