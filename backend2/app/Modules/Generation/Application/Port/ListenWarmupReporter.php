<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

/**
 * WHY THE LISTENING STEP WAS NOT OFFERED — the only trace a silent failure leaves.
 *
 * The step is optional, so its failure is deliberately invisible to the learner: no error screen,
 * no retry, the entry simply goes on to the date ({@see \App\Modules\Generation\Application\Service\PlanListenService}).
 * That is right for the person and terrible for anybody trying to find out whether the feature
 * works — «нам никогда не предлагали послушать» is otherwise indistinguishable from «этот шаг ещё
 * не выкачен».
 *
 * A port and not a `Log::` call for the reason every other one here is: the Application layer does
 * not import the framework, and the seam is what lets a test assert that the silence was recorded.
 * There are no counters and no storage — this is a log line with a contract, and the one thing it
 * must never grow is a way to reach the learner.
 */
interface ListenWarmupReporter
{
    /** @param string $reason short, in Russian, for the person reading the log at 8am */
    public function notOffered(string $userId, string $targetLang, string $reason): void;
}
