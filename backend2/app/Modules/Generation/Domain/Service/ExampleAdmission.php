<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use App\Modules\Generation\Domain\ValueObject\PlanDayItem;

/**
 * MAY THIS TERM BE GIVEN AN EXAMPLE SENTENCE AT ALL?
 *
 * One question, one answer, in Domain, because three places ask it and they used to disagree: the
 * day writer ({@see PlanShelf::wantsExample()}), the echo repair, and the learner's «Новый пример».
 *
 * A LINE is the sentence being learned. Its gap is cut out of its own `frame`, its speaking card is
 * graded against itself, and there is no trainer anywhere that reads an example beside it — so a
 * sentence written around a line is not a weaker example, it is a second sentence on a card that
 * teaches one. The live day of 02.09 is what this is written from: the day itself wrote none, and
 * the echo repair — whose whole job is «a term with no example is broken, buy it one» — then bought
 * an example for all thirteen lines of it, at one model call each. «I see, without utilities.» came
 * back taught by «When the power went out, I realized that I see, without utilities, life becomes…».
 *
 * A term with NO kind is an ordinary catalogue term and keeps its example: everything written
 * before plans existed has no `kind`, and «unknown» must not read as «line».
 */
final class ExampleAdmission
{
    /** @param string|null $kind `terms.kind` — `line`, `word`, `chunk`, `number` or null */
    public static function allows(?string $kind): bool
    {
        return $kind !== PlanDayItem::KIND_LINE;
    }
}
