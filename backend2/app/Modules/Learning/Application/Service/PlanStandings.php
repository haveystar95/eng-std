<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Application\Port\PlanStandingsReader;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Vocabulary\Application\Dto\TermContentView;
use DateTimeZone;

/**
 * WHERE EVERY WORD OF A PLAN STANDS — the one place the four filters meet.
 *
 * The Domain ladder ({@see PlanStageLadder}) is pure and knows nothing about this learner's
 * settings or this term's data, which is what makes it testable. Somebody has to hand it the list
 * of trainers a given word can ACTUALLY be dealt right now, and that list is an intersection of
 * four independent facts, each owned by something else:
 *
 *   the plan ladder     which trainers this stage deals at all            PlanStageLadder
 *   the level           which of them are open at this level              learning_mode_settings, scope=plan
 *   the learner         which trainers are switched on for them           learning_mode_settings, scope=global
 *   the term            which can be built from this term's content       TermPlayability
 *
 * Keeping them apart and intersecting HERE is the same discipline the ordinary session already
 * follows ({@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission}: enabled ∧ playable ∧
 * admitted). The alternative — teaching the ladder about content — would make «why is this word
 * stuck» a question with four possible answers and no way to tell them apart.
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
        private PlanStageLadder $ladder = new PlanStageLadder(),
    ) {}

    /**
     * @param  list<string>  $termIds
     * @param  array<string, TermContentView>  $content  hydrated content, keyed by term id
     * @param  string  $today  the learner's local day, `Y-m-d`
     * @return array<string, PlanTermStanding>  term id => standing (only for terms with content)
     */
    public function forTerms(
        UserId $user,
        PlanLevel $level,
        array $termIds,
        array $content,
        string $today,
        DateTimeZone $tz,
    ): array {
        if ($termIds === []) {
            return [];
        }

        $facts = $this->reader->factsFor($user, $termIds, $tz);
        $introduced = $this->reader->introducedAmong($user, $termIds);
        $openAtLevel = $this->planSettings->openModesFor($level);
        $enabled = $this->enabledModes->forUser($user);

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
                applicable: $this->applicableFor($termContent, $openAtLevel, $enabled, $kind, $termId),
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
     * The four filters, intersected, order preserved from the plan ladder.
     *
     * @param  list<ExerciseMode>  $openAtLevel
     * @return list<ExerciseMode>
     */
    private function applicableFor(
        TermContentView $content,
        array $openAtLevel,
        \App\Modules\Learning\Domain\ValueObject\EnabledModes $enabled,
        string $kind,
        string $termId,
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

        return array_values(array_filter(
            $openAtLevel,
            static fn (ExerciseMode $mode): bool => isset($forKind[$mode->value])
                && $forLanguage->has($mode)
                && $playable->supports($mode),
        ));
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
