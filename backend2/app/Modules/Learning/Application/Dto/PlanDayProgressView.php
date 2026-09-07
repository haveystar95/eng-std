<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Vocabulary\Application\Dto\TermContentView;

/** One day of a plan with its words' standings worked out. */
final readonly class PlanDayProgressView
{
    /**
     * @param  list<string>  $termIds
     * @param  array<string, PlanTermStanding>  $standings  term id => standing
     * @param  array<string, TermContentView>  $content     term id => content, read through THIS
     *                                                      day's collection so a term shows the
     *                                                      example written for this day's situation
     */
    public function __construct(
        public int $index,
        public ?string $collectionId,
        public array $termIds,
        public array $standings,
        public array $content,
        /**
         * «ДЕНЬ ПРОЙДЕН» — все три обязательных этапа пройдены НАСКВОЗЬ (решение владельца 07.09,
         * наряд DAY-GATE-1): «Слова и фразы», «Разговор» и «Скажи сам»
         * ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage::passed()}).
         *
         * Раньше здесь стояло «каждое слово дня закрыло ступень A», и это оказалось не тем вопросом.
         * Лестница (A/B/C) — проекция журнала, «что эта пара ещё не умеет»; прохождение дня — факт,
         * что человек дошёл до конца. Реплика, отвеченная сегодня неверно, день больше не держит:
         * по правилу «один показ ступени в день» она вернётся завтра, а вечер, из которого нет
         * выхода, — это то, что живой прогон 07.09 и показал.
         */
        public bool $passed,
        /**
         * THE SCENE, as much of it as a CARD needs — the вводка's own text, the scene's name, and
         * every ability of the scene by the `skill_ref` its cards point at.
         *
         * Carried here rather than re-read by the session builder because it is the same day object
         * this view was built from, and two reads of one model-written JSON blob is two chances to
         * disagree about what the scene said. Its one reader is the situational card
         * ({@see \App\Modules\Learning\Domain\Service\SituationalPrompt}).
         */
        public ?string $sceneIntro = null,
        public ?string $sceneTitle = null,
        /** @var array<string, string> `skill_ref` => the ability's `outcome`, support language */
        public array $skillOutcomes = [],
        /**
         * THE ORDER THIS SCENE IS SPOKEN IN, as the day stored it — or NULL on a day written before
         * P2 v0.5 (наряд DAY-2).
         *
         * Carried beside the scene for the same reason the вводка is: the session builder is
         * holding this day object already, and a second read of the same row is a second chance to
         * disagree about what the day said. Null is not «no dialogue» — it is «no stored one», and
         * {@see \App\Modules\Learning\Domain\Service\PlanDialogueChain} pairs the shelves instead.
         *
         * @var list<array{turn: string, term_id: string}>|null
         */
        public ?array $dialogue = null,
        /**
         * ЭТАПЫ ЭТОГО ДНЯ со своими состояниями — три обязательных, в порядке
         * ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage::stages()}).
         *
         * Здесь их ровно три: «Повторить ошибки» — строка ЭКРАНА, а не ворота, и его добавляет
         * перепись дня ({@see \App\Modules\Learning\Application\Service\PlanDayStateCensus}), которая
         * одна и знает про сегодняшние промахи. Прогресс плана считает то, от чего зависят замок и
         * фокус, и ничего сверх.
         *
         * @var list<array{stage: \App\Modules\Learning\Domain\ValueObject\PlanDayStage, state: \App\Modules\Learning\Domain\ValueObject\PlanDayStageState, cards: int}>
         */
        public array $stages = [],
    ) {}

    /**
     * Тот же день, но уже посчитанный машиной этапов.
     *
     * Прохождение считается ПОСЛЕ того, как стойки собраны — прогон сцены нужно спросить у журнала
     * прогонов, а свои ходы сцены читаются из этой самой цепочки, — поэтому вид рождается без
     * вердикта и получает его вторым проходом. Копией, а не мутацией: DTO остаётся readonly.
     *
     * @param  list<array{stage: \App\Modules\Learning\Domain\ValueObject\PlanDayStage, state: \App\Modules\Learning\Domain\ValueObject\PlanDayStageState, cards: int}>  $stages
     */
    public function withPassage(bool $passed, array $stages): self
    {
        return new self(
            index: $this->index,
            collectionId: $this->collectionId,
            termIds: $this->termIds,
            standings: $this->standings,
            content: $this->content,
            passed: $passed,
            sceneIntro: $this->sceneIntro,
            sceneTitle: $this->sceneTitle,
            skillOutcomes: $this->skillOutcomes,
            dialogue: $this->dialogue,
            stages: $stages,
        );
    }
}
