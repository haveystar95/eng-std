<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ListenWarmupBrief;
use App\Modules\Learning\Application\Dto\ListenWarmupView;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\ListenWarmupPort;

/**
 * The listening warm-up, asked for and handed back — and nothing is stored.
 *
 * Deliberately a command that writes nothing. The lines belong to a step the learner may skip, of a
 * plan that does not exist; the only durable trace the call leaves is its ledger row, written by
 * the service. What the learner then TAPS («Понял» / «Не совсем») rides back with `POST /plans` as
 * the plan's diagnostics — that is where it becomes a fact worth keeping, because that is where a
 * plan starts existing.
 *
 * A command rather than a query even though it reads: it SPENDS MONEY. The rule in this codebase is
 * that a query is free and repeatable, and the screen that polls a query must never be able to buy
 * a model call (same reasoning as `generate` vs `rebuild` on a plan day).
 */
final readonly class BuildListenWarmupHandler
{
    public function __construct(
        private ListenWarmupPort $listen,
        private LearnerProfileReader $profiles,
    ) {}

    public function __invoke(BuildListenWarmup $command): ListenWarmupView
    {
        return $this->listen->warmupFor(new ListenWarmupBrief(
            userId: $command->actorId->value,
            goalText: trim($command->goalText),
            supportLang: $this->profiles->nativeLangFor($command->actorId),
            // May be EMPTY — see the command. An empty one asks for the continuations alone.
            targetLang: $command->targetLang,
            level: $command->level,
        ));
    }
}
