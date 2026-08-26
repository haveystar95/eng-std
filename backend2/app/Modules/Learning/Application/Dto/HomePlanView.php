<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * THE HOME SCREEN'S DAY, in one read model.
 *
 * The screen asks one question — «что мне делать прямо сейчас и сколько это займёт» — and every
 * block on it is an answer to a part of that. Assembling them here rather than letting the client
 * derive them from a pile of counters is the point: the numbers on the screen are then the numbers
 * the planner would actually act on, and a state the design draws is a state the server names.
 *
 * A block the learner has nothing for is NULL or an empty list, never a zero: «0 слов» is not
 * something this screen ever says, and a nullable field is how that rule survives a redesign.
 */
final readonly class HomePlanView
{
    /**
     * @param  list<HomeEdgeTermView>  $edge     soonest-to-fall words; empty when none are near
     * @param  list<HomeHardTermView>  $hardest  what the last run missed; empty when it missed nothing
     */
    public function __construct(
        public HomeState $state,
        public HomeSessionView $session,
        public HomeInWorkView $inWork,
        public array $edge,
        public ?HomeTodayView $today,
        public ?HomeNextReviewView $nextReview,
        public array $hardest,
        public ?HomeContinueView $unfinished,
        public HomeStoreView $store,
    ) {}
}
