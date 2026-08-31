<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

/**
 * WHICH PLAN a collection is a day of, by the plan's own name.
 *
 * A plan day owns an ordinary collection, titled after the day — «Заселиться в отель · Поесть в
 * кафе». That title is the right thing to show ON the day and the wrong thing to show anywhere
 * else: a learner meeting one of its words months later, inside another plan's lesson, has never
 * seen that folder and did not make it. What they recognise is the PLAN — «Отпуск в Италии».
 *
 * A read model and not a repository: no entity, no transitions, one join answered in bulk. Empty
 * for a collection that is nobody's day, which is every ordinary folder and is the common case.
 */
interface PlanDayCollectionTitles
{
    /**
     * @param  list<string>  $collectionIds
     * @return array<string, string>  collection id => the title of the plan whose day it is
     */
    public function titlesByDayCollection(array $collectionIds): array;
}
