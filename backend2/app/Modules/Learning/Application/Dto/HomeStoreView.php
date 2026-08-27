<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * «…или взять из 17 готовых» — the store as the home screen needs it: a number, a taste, a window.
 *
 * [items] is the window (кадр 19-2's strip of covers, кадр 19-3's two big ones): real photographs,
 * because a picture of an airport sells «Аэропорт» and the word «Аэропорт» does not. [topics] is the
 * same preview in its older, thinner shape — titles only — kept so a phone built before the covers
 * existed still gets the three chips it knows how to draw.
 */
final readonly class HomeStoreView
{
    /**
     * @param  list<string>             $topics
     * @param  list<HomeStoreItemView>  $items
     */
    public function __construct(
        public int $count,
        public array $topics,
        public array $items = [],
    ) {}
}
