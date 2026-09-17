<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Exception\CoreSceneNotRemovable;
use App\Modules\Plan\Domain\Exception\PlanDayLocked;
use App\Modules\Plan\Domain\Exception\PlanTooShort;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\SceneKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE SHORTENING RULE (docs/plan-v2.md §7): an event nearer than the days asked for shortens the
 * plan; a shorter plan drops variants first, then the situation with the highest priority number;
 * the core (priority 1) is never dropped; a scene taken out in the preview leaves a review day
 * and the plan keeps its length.
 */
function shrPlan(int $days, ?string $eventDate = null, string $today = '2026-09-10'): Plan
{
    $plan = Plan::create(
        id: PlanId::generate(),
        userId: UserId::generate(),
        goalText: 'Иду к врачу с ребёнком',
        targetLang: new LanguageCode('en'),
        nativeLang: new LanguageCode('ru'),
        level: PlanLevel::Beginner,
        daysRequested: $days,
        eventDate: $eventDate === null ? null : new DateTimeImmutable($eventDate),
        today: new DateTimeImmutable($today),
        now: new DateTimeImmutable($today.'T10:00:00Z'),
        dayIds: static fn (): PlanDayId => PlanDayId::generate(),
    );

    return $plan;
}

function shrBlueprint(Plan $plan): void
{
    $request = new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, PlanCalendar::scenesCount($plan->daysTotal()));
    $blueprint = (new BlueprintParser)->parse(FakePlanModel::planPayload($request));
    $plan->acceptBlueprint($blueprint, new ModelCall('plan-builder-v2', 'test', 'fake', '0.000000', 1, 1), [], static fn (): PlanSceneId => PlanSceneId::generate());
}

function shrTitles(Plan $plan): array
{
    $titles = [];
    foreach ($plan->days() as $day) {
        $scene = $plan->sceneOf($day);
        $titles[] = $day->type()->value.($scene === null ? '' : ':'.$scene->priority().($scene->kind() === SceneKind::Variant ? 'v' : ''));
    }

    return $titles;
}

it('shortens the plan to the days left before the event, keeping what was asked', function () {
    $plan = shrPlan(10, '2026-09-14');

    expect($plan->daysTotal())->toBe(4)
        ->and($plan->daysRequested())->toBe(10)
        ->and($plan->daysShortenedFrom())->toBe(10)
        ->and(count($plan->days()))->toBe(4);
});

it('keeps the plan length when the event is far enough away', function () {
    $plan = shrPlan(5, '2026-12-01');

    expect($plan->daysTotal())->toBe(5)->and($plan->daysShortenedFrom())->toBeNull();
});

it('assigns the scenes to the scene days in order and marks the core', function () {
    $plan = shrPlan(5);
    shrBlueprint($plan);

    // 5 days → [scene, scene, review, scene, rehearsal]; the fake makes scene 2 the core (priority 1).
    expect(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'review', 'scene:3', 'rehearsal'])
        ->and(count(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isCore())))->toBe(1);
});

it('drops variants first when the plan shrinks, then the highest priority number, never the core', function () {
    // 8 days → 5 scenes: three situations (2, 1, 3) and two variants (4v, 5v).
    $plan = shrPlan(8);
    shrBlueprint($plan);
    expect(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'review', 'scene:3', 'scene:4v', 'review', 'scene:5v', 'rehearsal']);

    // Down to 5 days → 3 scene slots: both variants go first.
    $result = $plan->reschedule(null, 5, new DateTimeImmutable('2026-09-10'), static fn (): PlanDayId => PlanDayId::generate());
    expect($result['scenes_to_add'])->toBe(0)
        ->and(count($result['dropped_scene_ids']))->toBe(2)
        ->and(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'review', 'scene:3', 'rehearsal']);

    // Down to 3 days → 2 slots: of the situations left, priority 3 goes; 1 and 2 stay in order.
    $plan->reschedule(null, 3, new DateTimeImmutable('2026-09-10'), static fn (): PlanDayId => PlanDayId::generate());
    expect(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'rehearsal']);

    // Down to 1 day: the core is what is left.
    $plan->reschedule(null, 1, new DateTimeImmutable('2026-09-10'), static fn (): PlanDayId => PlanDayId::generate());
    expect(shrTitles($plan))->toBe(['scene:1'])
        ->and($plan->scenes()[0]->isCore())->toBeTrue();
});

it('asks for more scenes when the plan grows, and reports how many', function () {
    $plan = shrPlan(3);
    shrBlueprint($plan);

    $result = $plan->reschedule(null, 6, new DateTimeImmutable('2026-09-10'), static fn (): PlanDayId => PlanDayId::generate());

    // 6 days → 4 scene slots, 2 scenes in hand.
    expect($result['scenes_to_add'])->toBe(2)
        ->and(count($plan->days()))->toBe(6)
        ->and($plan->day(5)->type())->toBe(DayType::Scene)
        ->and($plan->day(5)->sceneId())->toBeNull();
});

it('turns a removed scene day into a review day without changing the length', function () {
    $plan = shrPlan(5);
    shrBlueprint($plan);
    $variant = null;
    foreach ($plan->scenes() as $scene) {
        if (! $scene->isCore()) {
            $variant = $scene;
        }
    }

    $plan->removeScene($variant->id());

    expect(count($plan->days()))->toBe(5)
        ->and(count($plan->scenes()))->toBe(2)
        ->and(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'review', 'review', 'rehearsal']);
});

it('never lets the core be removed from the preview', function () {
    $plan = shrPlan(3);
    shrBlueprint($plan);
    $core = array_values(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isCore()))[0];

    expect(fn () => $plan->removeScene($core->id()))->toThrow(CoreSceneNotRemovable::class);
});

it('cannot be shortened below the days already walked', function () {
    $plan = shrPlan(5);
    shrBlueprint($plan);
    planWriteLessons($plan);
    $today = new DateTimeImmutable('2026-09-10');
    $now = new DateTimeImmutable('2026-09-10T10:00:00Z');
    $plan->start($now, $today);
    $plan->openDay(1, $today, $now);
    $plan->closeDay(1, DayMetrics::empty(), $today, $now);
    $plan->openDay(2, $today->modify('+1 day'), $now->modify('+1 day'));

    expect(fn () => $plan->reschedule(null, 1, $today->modify('+1 day'), static fn (): PlanDayId => PlanDayId::generate()))
        ->toThrow(PlanTooShort::class);

    // Two days walked, cut to two: allowed, and the walked days are untouched.
    $plan->reschedule(null, 2, $today->modify('+1 day'), static fn (): PlanDayId => PlanDayId::generate());
    expect(count($plan->days()))->toBe(2)
        ->and($plan->day(1)->isClosed())->toBeTrue()
        ->and($plan->day(2)->isTouched())->toBeTrue();
});

it('opens days one per calendar day: day two waits for tomorrow', function () {
    $plan = shrPlan(3);
    shrBlueprint($plan);
    planWriteLessons($plan);
    $today = new DateTimeImmutable('2026-09-10');
    $now = new DateTimeImmutable('2026-09-10T10:00:00Z');
    $plan->start($now, $today);
    $plan->openDay(1, $today, $now);

    expect(fn () => $plan->openDay(2, $today, $now))->toThrow(PlanDayLocked::class);

    $plan->closeDay(1, DayMetrics::empty(), $today, $now);
    expect(fn () => $plan->openDay(2, $today, $now))->toThrow(PlanDayLocked::class)
        ->and($plan->day(2)->opensOn()?->format('Y-m-d'))->toBe('2026-09-11');

    $plan->openDay(2, $today->modify('+1 day'), $now->modify('+1 day'));
    expect($plan->day(2)->status()->value)->toBe('in_progress');
});

it('appends extension scenes after the existing ones whatever the model numbered them', function () {
    $plan = shrPlan(3);
    shrBlueprint($plan);
    $result = $plan->reschedule(null, 6, new DateTimeImmutable('2026-09-10'), static fn (): PlanDayId => PlanDayId::generate());
    expect($result['scenes_to_add'])->toBe(2);

    // The model answers the extension as if from scratch: orders 1..2 and a fresh core.
    $request = new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, 2);
    $more = (new BlueprintParser)->parse(FakePlanModel::planPayload($request))->scenes;
    expect(array_map(static fn ($s): int => $s->order, $more))->toBe([1, 2])
        ->and(array_map(static fn ($s): int => $s->priority, $more))->toContain(1);

    $plan->appendScenes($more, new ModelCall('plan-builder-v2', 'test', 'fake', '0.010000', 1, 1), static fn (): PlanSceneId => PlanSceneId::generate());

    $orders = array_map(static fn (PlanScene $s): int => $s->order(), $plan->scenes());
    $priorities = array_map(static fn (PlanScene $s): int => $s->priority(), $plan->scenes());
    expect($orders)->toBe([1, 2, 3, 4])
        ->and(count(array_unique($priorities)))->toBe(4)
        ->and(count(array_filter($plan->scenes(), static fn (PlanScene $s): bool => $s->isCore())))->toBe(1)
        ->and(shrTitles($plan))->toBe(['scene:2', 'scene:1', 'review', 'scene:3', 'scene:4', 'rehearsal'])
        // The plan call's cost is the sum of both calls, the attempts too.
        ->and($plan->planCall()?->costUsd)->toBe('0.010000')
        ->and($plan->planCall()?->attempts)->toBe(2);
});
