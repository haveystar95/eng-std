<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\ModeFallbackReporter;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Port\PlanStandingsReader;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Shared\Domain\Service\DistractorFamily;
use App\Modules\Shared\Domain\Service\DistractorLength;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use DateTimeZone;

/**
 * WHERE EVERY WORD OF A PLAN STANDS — the one place the five filters meet.
 *
 * The Domain ladder ({@see PlanStageLadder}) is pure and knows nothing about this learner's
 * settings or this term's data, which is what makes it testable. Somebody has to hand it the list
 * of trainers a given word can ACTUALLY be dealt right now, and that list is an intersection of
 * five independent facts, each owned by something else:
 *
 *   the plan ladder     which trainers this stage deals at all            PlanStageLadder
 *   the level           which of them are open at this level              learning_mode_settings, scope=plan
 *   the learner         which trainers are switched on for them           learning_mode_settings, scope=global
 *   the term            which can be built from this term's content       TermPlayability
 *   the POOL            whether a full choice can be built of its shape   DistractorFamily
 *
 * The fifth arrived with Д-2 and it is here rather than in the card assembler for one reason: a
 * checklist step is closed by an ANSWER. A step whose card can never be dealt is a stage that never
 * closes, so it must not be OWED — dropping it late, at the moment of dealing, would leave the day
 * un-passable and day n+1 unwritten. {@see choiceIsAffordable()}.
 *
 * Keeping them apart and intersecting HERE is the same discipline the ordinary session already
 * follows ({@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission}: enabled ∧ playable ∧
 * admitted). The alternative — teaching the ladder about content — would make «why is this word
 * stuck» a question with four possible answers and no way to tell them apart.
 *
 * ## THE LADDER OF A PLAN IS THE PLAN'S OWN
 *
 * A standing is a projection over the review log, and the log is keyed by (user, term) — which is
 * right, because progress is. It is NOT right as the input to a plan's ladder. Terms are globally
 * deduplicated, so «Sorry, could you repeat that?» in a plan started today is the same row as the
 * one a plan abandoned on Sunday used, and as the one sitting in the learner's notebook. Fed the
 * whole log, the new plan read Sunday's answers as its own: the card opened on the assembly step
 * with no introduction, and one card of the owner's day 1 — answered to `graduated` in a plan he
 * cancelled that morning — owed nothing at all and vanished from the sitting (01.09, PLAN-FIX-4 Ч.0).
 *
 * So the ladder counts for the pair (PLAN, term): only what happened after the card joined THIS
 * plan is evidence about it. A new card of a new plan starts at the introduction whatever the same
 * word has been through elsewhere, and — the other half of the same sentence — the notebook's own
 * standing is not touched, because nothing is written here at all. The cutoff is the moment the
 * term joined the DAY'S COLLECTION ({@see \App\Modules\Collections\Application\Port\UserCollectionTermsReader::joinedAtForCollection()}),
 * which is a date Collections already holds: a plan day IS a collection written at one instant.
 *
 * A cutoff rather than «answers given inside this plan's sessions» on purpose. The precise reading
 * would scope the log by session, and a review that arrives with no session id — an offline batch
 * replayed after a reinstall — would then close no step ever, which is a stage that never closes,
 * a day that never passes and a plan that stops generating. The cutoff fails the other way: at
 * worst it counts an answer given to the same word elsewhere in the same hour, and a plan holds its
 * own words out of every other session while it runs ({@see \App\Modules\Learning\Infrastructure\Eloquent\PlanHeldTerms})
 * so there is almost nowhere for such an answer to come from.
 *
 * One caveat worth stating: the LANGUAGE gate can empty the set completely (`zh`/`ja` carry no
 * trainer at all in v1). A word with no applicable trainer has no card it could ever be dealt, so
 * the ladder walks it straight through all three stages and calls it finished. That is the honest
 * outcome — the alternative is a plan that can never be completed — and it is why a plan in such a
 * pair would report readiness it did not earn. No such plan can be created today; when one can, this
 * is the line that has to change.
 */
final readonly class PlanStandings
{
    public function __construct(
        private PlanStandingsReader $reader,
        private PlanModeSettingsReader $planSettings,
        private EnabledModesReader $enabledModes,
        private StudyCardAssembler $assembler,
        private ModeFallbackReporter $fallbacks,
        private PlanStageLadder $ladder = new PlanStageLadder(),
        private DistractorLength $length = new DistractorLength(),
    ) {}

    /**
     * @param  list<string>  $termIds
     * @param  array<string, TermContentView>  $content  hydrated content, keyed by term id
     * @param  string  $today  the learner's local day, `Y-m-d`
     * @param  array<string, \DateTimeImmutable>  $since  term id => the moment this card joined THIS
     *         plan. See {@see class docblock, «The ladder of a plan is the plan's own»}.
     * @return array<string, PlanTermStanding>  term id => standing (only for terms with content)
     */
    public function forTerms(
        UserId $user,
        PlanLevel $level,
        array $termIds,
        array $content,
        string $today,
        DateTimeZone $tz,
        array $since = [],
    ): array {
        if ($termIds === []) {
            return [];
        }

        $facts = $this->reader->factsFor($user, $termIds, $tz, $since);
        $introduced = $this->reader->introducedAmong($user, $termIds, $since);
        $openAtLevel = $this->planSettings->openModesFor($level);
        $enabled = $this->enabledModes->forUser($user);
        // HOW MANY CARDS OF EACH SHAPE THIS PLAN HOLDS — the fifth filter, and the one that keeps a
        // starved choice from being a step nobody can close. See {@see choiceIsAffordable()}.
        $optionCount = $this->planSettings->knobsFor($level)->mcOptions;

        $out = [];
        foreach ($termIds as $termId) {
            $termContent = $content[$termId] ?? null;
            if ($termContent === null) {
                // No content, no card, no standing. The caller drops the term from the session for
                // the same reason the ordinary assembler does — a term whose content never arrived
                // is out of the session entirely rather than out of only some of its cards.
                continue;
            }

            $kind = $termContent->kind ?? PlanStageLadder::KIND_WORD;

            $out[$termId] = $this->ladder->standingFor(
                applicable: $this->applicableFor($termContent, $openAtLevel, $enabled, $kind, $termId, $content, $optionCount, $user),
                facts: $facts[$termId] ?? [],
                introduced: $introduced[$termId] ?? false,
                today: $today,
                // What the card DOES in its day picks the checklist; the pair's own number picks
                // the one thing a plan varies (word bank or scramble). Both are read here rather
                // than inside the ladder, because Domain has no idea what a term id looks like.
                kind: $kind,
                pairCounter: self::pairCounterFor($termId),
            );
        }

        return $out;
    }

    /**
     * The FIVE filters, intersected, order preserved from the plan ladder.
     *
     * @param  list<ExerciseMode>  $openAtLevel
     * @param  array<string, TermContentView>  $pool  every term this plan stands on — what a choice
     *         card of this plan would be built out of {@see optionsAvailable()}
     * @return list<ExerciseMode>
     */
    private function applicableFor(
        TermContentView $content,
        array $openAtLevel,
        \App\Modules\Learning\Domain\ValueObject\EnabledModes $enabled,
        string $kind,
        string $termId,
        array $pool,
        int $optionCount,
        UserId $user,
    ): array {
        // Per CARD and not per session: a plan is one pair, but this is the same gate every other
        // read applies and applying it here keeps one answer to «which trainers exist for this word».
        $forLanguage = $enabled->forLanguage($content->lang);
        if ($forLanguage === null) {
            return [];
        }

        $playable = $this->assembler->playabilityOf($content);

        // The trainers this KIND is ever dealt, intersected with the level's open list. Without
        // this the level would keep offering `typing` to a spoken line: the level says what a
        // learner at this level meets, and the kind says what this card can be asked at all.
        $forKind = [];
        foreach (PlanStage::cases() as $stage) {
            foreach (PlanStageLadder::modesOf($stage, $kind, self::pairCounterFor($termId)) as $mode) {
                $forKind[$mode->value] = true;
            }
        }

        $own = $this->optionsAvailable($pool, $content);
        // Itself, plus one wrong answer per remaining slot. {@see choiceIsAffordable()}
        $affordable = $own >= $optionCount;
        // THE INTERLOCUTOR'S OWN LINE is understood, never produced. {@see PRODUCTION_MODES}
        $recognitionOnly = $content->speaker === self::SPEAKER_ROLE;

        return array_values(array_filter(
            $openAtLevel,
            function (ExerciseMode $mode) use ($forKind, $forLanguage, $playable, $affordable, $content, $user, $optionCount, $own, $recognitionOnly): bool {
                if (! isset($forKind[$mode->value]) || ! $forLanguage->has($mode) || ! $playable->supports($mode)) {
                    return false;
                }
                if ($recognitionOnly && in_array($mode, self::PRODUCTION_MODES, true)) {
                    return false;
                }
                if ($affordable || ! self::isChoice($mode)) {
                    return true;
                }

                $this->fallbacks->distractorStarved(
                    $user,
                    TermId::fromString($content->id),
                    $mode->value,
                    $optionCount,
                    $own,
                );

                return false;
            },
        ));
    }

    /** `terms.speaker` for a line the INTERLOCUTOR says. Kept as a literal — Learning does not import Vocabulary Domain. */
    private const SPEAKER_ROLE = 'role';

    /**
     * WHAT A `role` LINE IS NEVER ASKED TO DO.
     *
     * A line marked `speaker: role` is what the other person says — «Hello. What seems to be the
     * problem with your child?». It is in the day so the learner will UNDERSTAND it when it is said
     * to them; it is the one card of a plan they will never say. The live run dealt it as an
     * ordinary card and spent a word bank on it, so the learner assembled the doctor's question
     * word by word and then read it aloud (Д-8).
     *
     * So: recognition stays — meeting it, choosing its meaning, hearing it — and everything that
     * asks the learner to PRODUCE the sentence falls out. It falls out of the CHECKLIST, not out of
     * the deal, for the reason the whole class exists: a step that is owed and cannot be answered
     * is a stage that never closes.
     */
    private const PRODUCTION_MODES = [
        ExerciseMode::WordBank,
        ExerciseMode::Scramble,
        ExerciseMode::Typing,
        ExerciseMode::Speaking,
        ExerciseMode::Cloze,
        ExerciseMode::Dictation,
    ];

    /** The modes whose options come out of the pool, and which therefore starve with it. */
    private static function isChoice(ExerciseMode $mode): bool
    {
        return $mode === ExerciseMode::MultipleChoice || $mode === ExerciseMode::DescriptionMatch;
    }

    /**
     * CAN THIS PLAN DEAL THIS CARD A FULL CHOICE, out of its own kind and form? — {@see applicableFor()}
     *
     * The fifth filter, and the reason it has to be a FILTER rather than a late refusal: a checklist
     * step is closed by an ANSWER, so a step whose card can never be built is a stage that never
     * closes, a day that never passes and a day n+1 that is never written. A card the pool cannot
     * furnish must therefore not be OWED, not merely not dealt.
     *
     * Counted over the plan's own terms and nothing else, which makes it at least as strict as
     * {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorReader} — that one
     * also tops up from the catalogue. Strict in that direction on purpose: the checklist may drop a
     * card the reader could have built, and must never owe one it could not.
     *
     * How many options a choice card of THIS TARGET could actually be dealt, itself included.
     *
     * Two rules, both the ones the option reader applies, and both asked here for the same reason:
     * {@see DistractorFamily} (a word beside a word, a question beside questions) and
     * {@see DistractorLength} (and not one three times its length). It used to be a count per
     * FAMILY, computed once for the day — which was enough while shape was the whole rule, and stops
     * being enough the moment length is part of it, because «how many `word`s does this plan hold»
     * is the same number for `key` and for `accommodation` and the answer for the two differs.
     *
     * @param  array<string, TermContentView>  $pool  every term this plan stands on
     */
    private function optionsAvailable(array $pool, TermContentView $target): int
    {
        $family = DistractorFamily::of($target->kind, $target->text);
        $count = 0;

        foreach ($pool as $view) {
            if (DistractorFamily::of($view->kind, $view->text) === $family
                && $this->length->fits($target->kind, $target->text, $view->text)) {
                $count++;
            }
        }

        // The target counts itself: `fits()` is reflexive and the family is its own, so the loop has
        // already taken it. A pool that somehow does not contain the target is still a card of one.
        return max(1, $count);
    }

    /**
     * The pair's own stable number — the input to the one alternation a plan makes.
     *
     * A hash of the term id, and deliberately not anything that MOVES. A counter of answers so far
     * would deal `word_bank` on Monday and `scramble` on Tuesday, and the stage-A checklist would
     * never close, because the step that was ticked is not the step being offered.
     */
    public static function pairCounterFor(string $termId): int
    {
        return (int) (crc32($termId) % 1000);
    }
}
