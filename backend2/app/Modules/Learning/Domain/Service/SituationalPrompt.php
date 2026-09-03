<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;
use App\Modules\Learning\Domain\ValueObject\SituationalSituation;

/**
 * WHERE THE SITUATION COMES FROM — the rule, in one pure function, with no model call behind it.
 *
 * The наряд is explicit that this is шаблонная сборка: the situation is built out of what the day
 * ALREADY holds — its вводка, its abilities, its own role lines — and buys nothing. Two paths, and
 * the owner fixed both (SIT-1):
 *
 *   **the paired role line.** If this day has a «Тебе скажут» card serving the SAME ability
 *   (`skill_ref`) as the card being trained, the situation is the first sentence of the scene's
 *   вводка plus that line, on the language being learned, played aloud — кадр D-04 exactly. That is
 *   the honest shape of the moment: somebody said something to you, now answer it.
 *
 *   **the ability.** No such role line — a scene whose «Ты спросишь» serves an ability nobody asks
 *   about, a day written before `skill_ref` was checked — and the situation is the same first
 *   sentence plus the ability's own `outcome` («рассказать, что болит»). Still a position, still on
 *   the support language, still not a translation of anything.
 *
 * ## THE ONE THING THAT IS NEVER AN INPUT
 *
 * The reply's own translation. Not «is filtered out», not «is paraphrased» — it is not passed to
 * this class at all, so «подсказка не содержит перевод ответа» is a fact about the signature rather
 * than a rule a future edit could quietly break. Which is also why there is no generation here: the
 * only mechanical way to turn a translation into a situation is to print the translation, and that
 * card is a translation exercise wearing a scene's clothes.
 *
 * ## And why the pairing does not move between sittings
 *
 * A day may hold two role lines for one ability, and the learner must not be shown a different one
 * every time they come back to the same card — a rehearsal that changes its own premise is not a
 * rehearsal. The tie is broken by TERM ID: ULIDs are written in the order the day composed its
 * shelves, so «the first one on the shelf» and «the smallest id» are the same card, and that card
 * stays the same across sittings, across a reinstall and across a rebuilt session — with nothing
 * stored anywhere to be kept in step.
 *
 * Pure, in Domain, and it names no Vocabulary type: the caller flattens the day into
 * {@see SituationalCandidate}s.
 */
final class SituationalPrompt
{
    /** `terms.shelf` for the interlocutor's own lines — the only shelf a pair can be found on. */
    public const SHELF_HEAR = 'hear';

    /**
     * The situation for one card, or null when this mode has none (every trainer but the three).
     *
     * @param  list<SituationalCandidate>  $dayCards  every card of the day being studied, in any
     *         order — the pairing is decided by id, not by the order they arrive in
     * @param  array<string, string>  $skillOutcomes  `skill_ref` => the ability's own `outcome`,
     *         on the support language
     * @param  string|null  $sceneIntro  the scene's вводка, support language, 2–3 sentences
     * @param  string|null  $sceneTitle  the scene's name — what a hear card announces instead
     */
    public function for(
        ExerciseMode $mode,
        SituationalCandidate $card,
        array $dayCards,
        array $skillOutcomes,
        ?string $sceneIntro,
        ?string $sceneTitle,
    ): ?SituationalSituation {
        if (! $mode->isSituational()) {
            return null;
        }

        // «ТЕБЕ СКАЖУТ» announces the SCENE and nothing else: what is about to be heard is the
        // interlocutor's line itself, and printing a вводка over it would be answering the card's
        // own question before it is asked (наряд Ч-1: «контекст „что вы сейчас услышите“ из title
        // сцены»).
        if ($mode === ExerciseMode::SituationalHear) {
            return new SituationalSituation(
                source: SituationalSituation::SOURCE_SCENE,
                context: self::text($sceneTitle),
            );
        }

        $context = self::firstSentence($sceneIntro);
        $pair = $this->pairedRoleLine($card, $dayCards);

        if ($pair !== null) {
            return new SituationalSituation(
                source: SituationalSituation::SOURCE_ROLE_LINE,
                context: $context,
                roleLine: $pair->text,
                roleLineTermId: $pair->termId,
            );
        }

        return new SituationalSituation(
            source: SituationalSituation::SOURCE_SKILL,
            context: $context,
            task: self::text($skillOutcomes[$card->skillRef ?? ''] ?? null),
        );
    }

    /**
     * The day's «Тебе скажут» card that serves the SAME ability as this one — the smallest id among
     * them, which is the first one on the shelf. See the class docblock for why the tie is broken
     * that way and not by the order the caller happened to build the list in.
     *
     * A card with no `skill_ref` has no pair by definition: `null === null` would otherwise match
     * every unlabelled role line of a day written before the gate existed, which is a pairing made
     * of two absences.
     *
     * @param  list<SituationalCandidate>  $dayCards
     */
    private function pairedRoleLine(SituationalCandidate $card, array $dayCards): ?SituationalCandidate
    {
        $skillRef = self::text($card->skillRef);
        if ($skillRef === null) {
            return null;
        }

        $best = null;
        foreach ($dayCards as $candidate) {
            if ($candidate->shelf !== self::SHELF_HEAR
                || self::text($candidate->skillRef) !== $skillRef
                || self::text($candidate->text) === null) {
                continue;
            }
            if ($best === null || $candidate->termId < $best->termId) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * The вводка's first sentence — «Вы у стойки регистратуры.» — and only it.
     *
     * The вводка is 2–3 sentences and says three things (who is in front of you, what will happen,
     * what counts as success). Over a card, the last two are noise at best: the learner is IN the
     * moment already, and a card that re-explains the day every time it is dealt is a card nobody
     * reads. The split is on terminal punctuation and nothing cleverer, because the вводка is prose
     * a model wrote and any parsing beyond that would be guessing.
     */
    private static function firstSentence(?string $intro): ?string
    {
        $intro = self::text($intro);
        if ($intro === null) {
            return null;
        }

        $parts = preg_split('/(?<=[.!?…])\s+/u', $intro, 2);

        return $parts === false || $parts === [] ? $intro : trim($parts[0]);
    }

    private static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
