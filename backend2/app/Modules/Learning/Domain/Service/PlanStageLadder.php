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
    /** What a plan card DOES in its day — the third dimension of the checklist below. */
    public const KIND_LINE = 'line';

    public const KIND_WORD = 'word';

    public const KIND_CHUNK = 'chunk';

    /**
     * THE «ПОНИМАЮ» LADDER — not a kind of card but a TIER of one, and the only one that is not a
     * `speak` ladder in disguise.
     *
     * Канон §3: «Понимаю» — два касания: услышал → выбрал смысл; узнал в тексте. Без ступени C, без
     * говорения. Every card of the «Тебе скажут» shelf climbs it, and so would a number if numbers
     * were dealt yet (NUM-1).
     *
     * It is expressed as a ladder KEY rather than as a filter over the line ladder because the two
     * are different lists, not one list minus some entries: an understanding card is met, its
     * meaning is chosen, and it is heard — three steps over two stages, where a spoken line has
     * seven over two. Subtracting the production modes from the spoken ladder gave the same answer
     * by accident until v0.4, and «by accident» is what {@see RoleLineModes} was written twice to
     * stop being the mechanism.
     */
    public const KIND_UNDERSTAND = 'understand';

    /**
     * THE CHECKLISTS, by stage and by what the card IS.
     *
     * One config, in Domain, next to the stages it spells out. Until v0.2 there was one ladder for
     * everything a plan day produced, and it was the WORD's ladder: a spoken line got typing and
     * dictation, so the learner was asked to type «My back has been hurting for a week.» letter
     * for letter — a memory test of punctuation, not of the ability the day promised. And a line
     * has no stage C at all, because stage C is «nothing on the screen, produce it exactly», which
     * for a sentence is the wrong ask at any level below fluent.
     *
     *   line   A  meet it → recognise it → put it together (word bank OR scramble, alternating) →
     *             read it aloud
     *          B  fill its own gap → hear it → say it with nothing on the screen
     *          C  —
     *   word   A  meet it → recognise it twice → say the word
     *          B  fill the gap in the day's frame → type it
     *          C  take it down by ear → tell a right sentence from a wrong one
     *   chunk  the word's ladder exactly. A connector is a substitution by function, and the only
     *          difference is where its gap is cut, which is the session's business and not this
     *          table's.
     *
     * `multiple_choice` appears TWICE for a word on purpose — forward and reverse recognition are
     * the same trainer asked in two directions, exactly as the ordinary ladder's rungs 1 and 2 are,
     * and a word recognised once has been recognised once. A LINE gets one: a sentence recognised
     * in two directions is the same reading twice, and the assembly step is the second retrieval.
     *
     * @var array<string, array<string, list<ExerciseMode>>>
     */
    private const STEPS = [
        self::KIND_LINE => [
            PlanStage::A->value => [
                ExerciseMode::Intro,
                ExerciseMode::MultipleChoice,
                // WordBank or Scramble — see {@see assemblyModeFor()}. Not a random pick: the pair
                // decides, once, and keeps its answer for as long as the pair exists.
                ExerciseMode::WordBank,
                ExerciseMode::Speaking,
            ],
            PlanStage::B->value => [
                ExerciseMode::Cloze,
                ExerciseMode::Listening,
                ExerciseMode::Speaking,
            ],
            PlanStage::C->value => [],
        ],
        self::KIND_WORD => [
            PlanStage::A->value => [
                ExerciseMode::Intro,
                ExerciseMode::MultipleChoice,
                ExerciseMode::MultipleChoice,
                ExerciseMode::Speaking,
            ],
            PlanStage::B->value => [
                ExerciseMode::Cloze,
                ExerciseMode::Typing,
            ],
            PlanStage::C->value => [
                ExerciseMode::Dictation,
                ExerciseMode::PickCorrect,
            ],
        ],
        // TWO TOUCHES AND A NIGHT BETWEEN THEM, and nothing that asks for the sentence back.
        //
        //   A  meet it → choose what it means
        //   B  hear it and take it down
        //   C  —
        //
        // `listening` is the one production-looking mode that stays, and it stays for the reason
        // DECISIONS п. 223 gives: hearing a line said to you and writing down what you heard is
        // exactly the skill the shelf exists for. Everything that asks the learner to PRODUCE the
        // interlocutor's turn — the word bank, the speaking card, the dictation of a sentence they
        // will never say — is absent here and refused again downstream ({@see RoleLineModes}),
        // because a day opened out of turn never sees this checklist at all.
        self::KIND_UNDERSTAND => [
            PlanStage::A->value => [
                ExerciseMode::Intro,
                ExerciseMode::MultipleChoice,
            ],
            PlanStage::B->value => [
                ExerciseMode::Listening,
            ],
            PlanStage::C->value => [],
        ],
    ];

    /**
     * The alternative to the word bank in a LINE's stage A, chosen by the pair rather than by
     * chance.
     *
     * The наряд is explicit that there is NO random choice of exercise anywhere in a plan: variety
     * comes from alternation, and alternation needs something stable to alternate on. The pair's
     * own identity is that thing — the same word gives the same trainer today, tomorrow and after a
     * reinstall, so a checklist step that was closed stays closed. A counter that moved (the number
     * of answers so far, say) would deal `word_bank` on Monday, `scramble` on Tuesday, and the
     * stage would never close.
     */
    private const ASSEMBLY_ALTERNATIVES = [ExerciseMode::WordBank, ExerciseMode::Scramble];

    /** Misses in a row that soften the knobs for the rest of the stage. */
    public const SOFTEN_AFTER_ERRORS = 3;

    /**
     * Every trainer this stage deals to a card of this KIND, applicable or not — for the admin
     * screen and for the seed of the plan-scoped knob rows, which need to know the set before any
     * learner exists.
     *
     * @return list<ExerciseMode>
     */
    public static function modesOf(PlanStage $stage, string $kind = self::KIND_WORD, int $pairCounter = 0): array
    {
        $steps = self::STEPS[self::normalizeKind($kind)][$stage->value];

        return array_map(
            static fn (ExerciseMode $mode): ExerciseMode => $mode === ExerciseMode::WordBank
                ? self::assemblyModeFor($pairCounter)
                : $mode,
            $steps,
        );
    }

    /**
     * WORD BANK OR SCRAMBLE for this pair — the one place a plan varies what it deals, and it
     * varies by identity, never by chance. See {@see ASSEMBLY_ALTERNATIVES}.
     */
    public static function assemblyModeFor(int $pairCounter): ExerciseMode
    {
        return self::ASSEMBLY_ALTERNATIVES[abs($pairCounter) % count(self::ASSEMBLY_ALTERNATIVES)];
    }

    /**
     * A `chunk` rides the word's ladder, and anything unknown does too.
     *
     * Unknown is the ordinary case, not an error: every term written before plans existed has no
     * `kind`, and a plan that re-uses one has to deal it SOMETHING. The word's ladder is the
     * conservative answer — it is the longest of the three, so nothing is skipped by accident.
     */
    private static function normalizeKind(string $kind): string
    {
        return match ($kind) {
            self::KIND_LINE, self::KIND_UNDERSTAND => $kind,
            default => self::KIND_WORD,
        };
    }

    /**
     * WHICH LADDER A PLAN CARD CLIMBS — the one place the tier beats the kind.
     *
     * The shelf decides the tier and the tier decides the ladder (канон §3), so a card of «Тебе
     * скажут» takes the two-touch ladder however much it looks like a spoken line, and everything
     * else takes the ladder of what it is. Read by the checklist
     * ({@see \App\Modules\Learning\Application\Service\PlanStandings}) and by the session's
     * running order, from here, so the two cannot answer differently for one card.
     *
     * A term with no tier at all — everything written before v0.4, and every card outside a plan —
     * is judged by its kind exactly as it was.
     */
    public static function ladderKindFor(?string $kind, ?string $tier): string
    {
        if ($tier === self::TIER_UNDERSTAND) {
            return self::KIND_UNDERSTAND;
        }

        return self::normalizeKind($kind ?? self::KIND_WORD);
    }

    /** `understand` — the tier whose cards are met and heard and never produced. */
    public const TIER_UNDERSTAND = 'understand';

    /**
     * The stage after this one FOR THIS KIND, or null at the top.
     *
     * A line has no stage C, so B is its last: `finished` — «готовность слова» — is true for a line
     * one stage earlier than for a word, and that is the whole of the readiness rule
     * ({@see \App\Modules\Learning\Application\Query\GetPlanHandler::readinessOf()}).
     */
    public static function nextStageFor(PlanStage $stage, string $kind): ?PlanStage
    {
        $next = $stage->next();
        if ($next === null) {
            return null;
        }

        return self::STEPS[self::normalizeKind($kind)][$next->value] === [] ? null : $next;
    }

    /** The LAST stage a card of this kind lives on — B for a line, C for everything else. */
    public static function lastStageFor(string $kind): PlanStage
    {
        return self::normalizeKind($kind) === self::KIND_WORD ? PlanStage::C : PlanStage::B;
    }

    /**
     * Every trainer any stage deals to any kind, each once, in stage order.
     *
     * @return list<ExerciseMode>
     */
    public static function allModes(): array
    {
        $out = [];
        foreach ([self::KIND_LINE, self::KIND_WORD, self::KIND_UNDERSTAND] as $kind) {
            foreach (PlanStage::cases() as $stage) {
                foreach (self::STEPS[$kind][$stage->value] as $mode) {
                    if (! in_array($mode, $out, true)) {
                        $out[] = $mode;
                    }
                }
            }
        }

        // The assembly alternative is dealt in place of the word bank and is therefore never in the
        // table above; it is still a trainer a learner will meet, so the seed has to know about it.
        foreach (self::ASSEMBLY_ALTERNATIVES as $mode) {
            if (! in_array($mode, $out, true)) {
                $out[] = $mode;
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
     *   the two stage-A recognitions   the first is FORWARD, the second REVERSE — but ONLY when the
     *                                  level actually deals recognition cards. See below.
     *   speaking in stage A            the assembly rung, so the card asks for the WORD: translation
     *                                  on screen, say the term.
     *   speaking in stages B and C     the dictation rung, so the card asks for the EXAMPLE. Whether
     *                                  the sentence is on the screen is the stage's own answer
     *                                  ({@see PlanStage::speakingForm()}) and not a rung — the
     *                                  trainer is not asked to know about plans.
     *
     * ## Why the recognition rungs depend on the options policy
     *
     * Rung 1 is not merely «an early multiple_choice»: it is the card whose options are the
     * session's own neighbours and whose answer is graded by IDENTITY — the learner taps, the client
     * uploads the tapped TERM ID, and the server compares ids. That card is dealt only when the
     * options policy is `distant`, which for a plan means the level's `distractor_closeness` is
     * `far` ({@see \App\Modules\Learning\Domain\ValueObject\PlanKnobs::optionsPolicy()}).
     *
     * Claim rung 1 when the policy is `standard` and the two halves come apart: the assembler builds
     * an ordinary choice card whose answer is the term's TEXT, the card still carries rung 1, and
     * the server grades that text against a term id and returns `again`. The live S1 run did exactly
     * this — one word of nine was marked wrong for a correct answer, its stage-A checklist never
     * closed, and once the pair graduated the re-deal was rejected as a stale ladder answer, so the
     * day could not be finished at all.
     *
     * So the rung follows the SAME input as the card: no recognition options, no recognition rung.
     * Both stage-A choice cards are then ordinary ones at the assembly rung, which is two real
     * retrievals with real distractors — «×2» still means twice.
     *
     * @param  int  $occurrence  which appearance of this mode inside the stage, 1-based
     * @param  bool  $recognitionOptions  will the assembler deal the identity-graded recognition
     *                                    card for this learner — i.e. is the options policy
     *                                    `distant`?
     */
    public static function ladderStepFor(
        PlanStage $stage,
        ExerciseMode $mode,
        int $occurrence,
        bool $recognitionOptions,
        string $kind = self::KIND_WORD,
    ): int {
        if ($mode === ExerciseMode::Intro) {
            return LearningLadder::STEP_INTRO;
        }

        // A LINE is graded against ITSELF, at every stage. The dictation rung means «ask for the
        // example», and a line's example is the turn around it — a different sentence. What the
        // learner is asked to say is the line, so the rung stays the assembly one and the STAGE
        // says whether the text is on the screen ({@see PlanStage::speakingForm()}).
        // A LINE is graded against ITSELF, at every stage, and so is a card of the понимаю tier:
        // the dictation rung means «ask for the example», and their example is a different
        // sentence. What is asked for is the line, so the rung stays the assembly one.
        if (self::normalizeKind($kind) !== self::KIND_WORD) {
            return LearningLadder::STEP_ASSEMBLY;
        }

        if ($stage === PlanStage::A) {
            return match (true) {
                $mode !== ExerciseMode::MultipleChoice, ! $recognitionOptions => LearningLadder::STEP_ASSEMBLY,
                $occurrence <= 1 => LearningLadder::STEP_RECOGNITION_FORWARD,
                default => LearningLadder::STEP_RECOGNITION_REVERSE,
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
     * @param  string  $kind                     what this card DOES in its day — `line`, `word` or
     *                                           `chunk`. Picks the checklist; see {@see STEPS}.
     * @param  int  $pairCounter                 the pair's own stable number, which decides the one
     *                                           thing a plan varies ({@see assemblyModeFor()})
     */
    public function standingFor(
        array $applicable,
        array $facts,
        bool $introduced,
        string $today,
        string $kind = self::KIND_WORD,
        int $pairCounter = 0,
    ): PlanTermStanding {
        $stage = PlanStage::first();
        $cursor = 0;
        $lastStage = self::lastStageFor($kind);

        while (true) {
            $steps = $this->stepsFor($stage, $applicable, $kind, $pairCounter);
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
                    ready: $stage === $lastStage,
                );
            }

            $next = self::nextStageFor($stage, $kind);

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
                    ready: $stage === $lastStage,
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
                    ready: true,
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
    private function stepsFor(PlanStage $stage, array $applicable, string $kind, int $pairCounter): array
    {
        return array_values(array_filter(
            self::modesOf($stage, $kind, $pairCounter),
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
