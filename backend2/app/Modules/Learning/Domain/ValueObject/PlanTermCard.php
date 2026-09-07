<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ОДНА КАРТОЧКА СЦЕНЫ СО СВОЕЙ СТОЙКОЙ — вход машины этапов
 * ({@see \App\Modules\Learning\Domain\Service\PlanDayPassage}).
 *
 * Три факта, и ровно те три, от которых зависит «должна ли эта карточка что-нибудь сегодня и какому
 * этапу этот долг принадлежит»: полка (она решает секцию), вид на лестнице (он решает, раздаётся ли
 * ступень целиком) и сама стойка. Текста, перевода и картинки здесь нет намеренно — Domain не
 * должен знать, как карточка выглядит, чтобы ответить, пройден ли этап.
 */
final readonly class PlanTermCard
{
    public function __construct(
        public string $termId,
        /** `terms.shelf` — `words` · `chunks` · `hear` · `say` · `ask` · `numbers`, или null. */
        public ?string $shelf,
        /** Вид на лестнице плана — `word` · `chunk` · `line` ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}). */
        public string $kind,
        public PlanTermStanding $standing,
    ) {}
}
