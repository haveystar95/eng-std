<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;
use App\Modules\Learning\Domain\ValueObject\SituationalSituation;
use App\Modules\Learning\Domain\ValueObject\SituationalTurn;

/**
 * WHERE THE SITUATION COMES FROM — the rule, in one pure function, with no model call behind it.
 *
 * The наряд is explicit that this is шаблонная сборка: the situation is built out of what the day
 * ALREADY holds — its вводка, its abilities, its own role lines — and buys nothing. Two paths, and
 * the owner fixed both (SIT-1):
 *
 *   **the paired role line.** The situation is the first sentence of the scene's вводка plus the
 *   line the interlocutor says right before this move, on the language being learned, played
 *   aloud — кадр D-04 exactly. That is the honest shape of the moment: somebody said something to
 *   you, now answer it. Which line that is answers the DIALOGUE CHAIN of the day (канон §9), and
 *   only a day written before the chain existed falls back to matching on the ability (`skill_ref`).
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
 * ## Почему пару выбирает ЦЕПОЧКА, а не умение
 *
 * У дня бывает ДВЕ реплики роли на одно умение, и по `skill_ref` их не различить. Так и вышло на
 * телефоне владельца 08.09: карточка `ask` «How is ownership split across the team?» (умение s3.2)
 * получила в ситуацию «What were you personally responsible for?» — вторую реплику того же умения,
 * выигравшую тай-брейк по id, — и человеку предложили собрать ВОПРОС в ответ на вопрос. Цепочка
 * дня при этом говорила однозначно: перед этим ходом стоит «Do you have any questions for me?».
 *
 * Тай-брейк по id был не ошибкой, а честной попыткой выбрать хоть что-то, пока выбирать было
 * нечем: цепочки (`plan_day.dialogue`, P2 v0.5) тогда ещё не было. Теперь она есть и отвечает на
 * этот вопрос точно, поэтому гадание уходит вниз — в запасной путь для дней, написанных раньше.
 *
 * ## И почему пара не ездит между присестами
 *
 * Человек не должен видеть разную посылку у одной и той же карточки: репетиция, меняющая
 * собственную предпосылку, — не репетиция. Цепочка это держит по построению (она у дня одна и
 * записана раз), а запасной путь — тем же тай-брейком по TERM ID, что и раньше: ULID'ы пишутся в
 * порядке, в котором день собирал полки, так что «первая на полке» и «наименьший id» — одна и та
 * же карточка, и ничего хранить для этого не нужно.
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
     * @param  list<SituationalTurn>  $chain  цепочка разговора ЭТОГО дня, в порядке, в каком она
     *         звучит. Пустая — день написан до цепочки, и пара ищется по умению
     */
    public function for(
        ExerciseMode $mode,
        SituationalCandidate $card,
        array $dayCards,
        array $skillOutcomes,
        ?string $sceneIntro,
        ?string $sceneTitle,
        array $chain = [],
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
        $pair = $this->pairedRoleLine($card, $dayCards, $chain);

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
     * Реплика собеседника, стоящая ПЕРЕД этим ходом. Сначала по цепочке, потом — по умению.
     *
     * @param  list<SituationalCandidate>  $dayCards
     * @param  list<SituationalTurn>  $chain
     */
    private function pairedRoleLine(
        SituationalCandidate $card,
        array $dayCards,
        array $chain,
    ): ?SituationalCandidate {
        return $this->fromChain($card, $dayCards, $chain) ?? $this->fromSkill($card, $dayCards);
    }

    /**
     * ЧТО СКАЗАЛИ ПРЯМО ПЕРЕД ЭТИМ ХОДОМ — по цепочке дня, и это точный ответ, а не догадка.
     *
     * Ближайшая реплика роли ВЫШЕ по цепочке: обычно она стоит вплотную, но цепочка не обязана
     * идеально чередоваться (гейт `day.dialogue_not_alternating` фатален для НОВЫХ дней, а старые
     * живут), поэтому идём вверх, пока не встретим сторону роли.
     *
     * Тип обмена (`pair`: `answer` | `ask`) здесь не спрашивается намеренно: ближайшая реплика
     * роли выше по цепочке И ЕСТЬ пара, чем бы этот обмен ни был, — а сверять две вещи там, где
     * достаточно одной, значит завести второй способ ошибиться.
     *
     * Null, когда цепочки нет вовсе, когда этого хода в ней нет (карточка из другого присеста,
     * день старой сборки) или когда реплики роли выше не оказалось.
     *
     * @param  list<SituationalCandidate>  $dayCards
     * @param  list<SituationalTurn>  $chain
     */
    private function fromChain(
        SituationalCandidate $card,
        array $dayCards,
        array $chain,
    ): ?SituationalCandidate {
        $at = null;
        foreach ($chain as $i => $turn) {
            if ($turn->side === SituationalTurn::SIDE_YOU && $turn->termId === $card->termId) {
                $at = $i;
                break;
            }
        }
        if ($at === null) {
            return null;
        }

        for ($i = $at - 1; $i >= 0; $i--) {
            if ($chain[$i]->side !== SituationalTurn::SIDE_ROLE) {
                continue;
            }

            return self::cardById($chain[$i]->termId, $dayCards);
        }

        return null;
    }

    /**
     * ЗАПАСНОЙ ПУТЬ — день, написанный до цепочки: «Тебе скажут» того же умения, наименьший id
     * (первая на полке). См. докблок класса о том, почему это гадание и почему оно осталось внизу.
     *
     * A card with no `skill_ref` has no pair by definition: `null === null` would otherwise match
     * every unlabelled role line of a day written before the gate existed, which is a pairing made
     * of two absences.
     *
     * @param  list<SituationalCandidate>  $dayCards
     */
    private function fromSkill(SituationalCandidate $card, array $dayCards): ?SituationalCandidate
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
     * Карточка дня по id — с непустым текстом: реплика, которой нечего сказать, ситуацией не
     * становится (её место займёт запасной путь, а не пустой пузырь).
     *
     * @param  list<SituationalCandidate>  $dayCards
     */
    private static function cardById(string $termId, array $dayCards): ?SituationalCandidate
    {
        foreach ($dayCards as $candidate) {
            if ($candidate->termId === $termId && self::text($candidate->text) !== null) {
                return $candidate;
            }
        }

        return null;
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
