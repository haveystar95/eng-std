<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * WHICH HOME SCREEN the learner is looking at — decided here, once, from the day's facts.
 *
 * The four states are the four frames of the design (17a–17d), and naming them on the server is
 * deliberate: the client used to derive «на сегодня всё» from three counters and a goal comparison,
 * and any screen that re-derives a state re-derives it slightly differently. One field, one truth,
 * and a contract test per state.
 */
enum HomeState: string
{
    /** 17c — no collections, no pool, nothing ever answered. Only the two ways to get first words. */
    case Empty = 'empty';

    /** 17a — there is a session to run today. */
    case Plan = 'plan';

    /** 17b — the day's session is done and something was answered today. */
    case Done = 'done';

    /** 17d — nothing to do and nothing answered today: repeats are all scheduled ahead. */
    case Idle = 'idle';
}
