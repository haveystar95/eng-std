<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\Paywalls;
use App\Modules\Plan\Application\Service\PlanPaces;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\LanguagePairInvalid;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\LanguageRoles;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Creates the plan row with its calendar and queues the model call. The HTTP request never
 * waits on the model; the client polls the build.
 *
 * THE PAIR FIRST (наряд LANG-1 §7): the native is the learner's profile's (п. 159/180 — the request names
 * only the target), and a plan is refused with 422 `language_pair_invalid` when the target is not one of the
 * deployment's plan targets ({@see PlanConfig::$languages}), the native is not one of
 * `LanguageRoles::planNatives()`, or the two are one language. Asked before the paywall and before anything
 * is written: a pair the product cannot build is not a plan the learner spent.
 *
 * A native that is no language code at all is the same 422, not a 500 (наряд LANG-1, валидатор): `PUT /profile` took
 * any 2–5 characters before LANG-1 §7, so a profile may still hold «en_US» or «rus», and the calendar refuses to read
 * it as a language ({@see LearnerCalendar::nativeLangFor()}). No plan is read in it; `meta.native` is then empty — the
 * profile holds no language to name — and the learner sets theirs with `PUT /profile`, as for any native no plan is
 * read in. The stored value goes to the log in the problem's message.
 *
 * While the paywall is on (наряд ACC-1 §2) the learner may be refused next — a plan beyond the free one without a
 * subscription is 402 `plan_subscription_required`, a subscriber's fourth plan in work is 409 `plan_active_limit` —
 * asked in the transaction that writes the plan, the learner's plans held still, so two quick taps are asked one after
 * the other. The model is asked only once the plan is written.
 */
final readonly class CreatePlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
        private PlanPaces $paces,
        private Paywalls $paywalls,
        private TransactionManager $tx,
        private PlanConfig $config,
    ) {}

    public function __invoke(CreatePlan $command): PlanId
    {
        $now = $this->clock->now();
        $native = $this->nativeOf($command);
        $this->assertPair($command->targetLang, $native);

        $plan = $this->tx->run(function () use ($command, $native, $now): Plan {
            $this->paywalls->assertMayCreate($command->actorId);
            $plan = $this->plan($command, $native, $now);
            $this->plans->save($plan);

            return $plan;
        });
        $this->dispatcher->buildPlan($plan->id());

        return $plan->id();
    }

    /**
     * The learner's native, from the profile — a stored value that is no language code refused as the pair it makes.
     *
     * @throws LanguagePairInvalid
     */
    private function nativeOf(CreatePlan $command): LanguageCode
    {
        try {
            return $this->calendar->nativeLangFor($command->actorId);
        } catch (InvalidArgumentException $e) {
            throw new LanguagePairInvalid(
                "No plan is built into {$command->targetLang->value} from the profile's native: {$e->getMessage()}.",
                ['target' => $command->targetLang->value, 'native' => ''],
            );
        }
    }

    /** @throws LanguagePairInvalid */
    private function assertPair(LanguageCode $target, LanguageCode $native): void
    {
        if (! in_array($target->value, $this->config->languages, true)
            || ! in_array($native->value, LanguageRoles::planNatives(), true)
            || $native->equals($target)) {
            throw LanguagePairInvalid::of($target->value, $native->value);
        }
    }

    private function plan(CreatePlan $command, LanguageCode $native, DateTimeImmutable $now): Plan
    {
        return Plan::create(
            id: PlanId::generate(),
            userId: $command->actorId,
            goalText: $command->goalText,
            targetLang: $command->targetLang,
            nativeLang: $native,
            level: $command->level,
            daysRequested: $command->daysTotal,
            eventDate: $command->eventDate,
            today: $this->calendar->todayFor($command->actorId, $now),
            now: $now,
            dayIds: static fn (): PlanDayId => PlanDayId::generate(),
            // The price list of its days as it stands today (наряд FIX-3 §2).
            pace: $this->paces->current(),
        );
    }
}
