<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Exception\PlanNotInState;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE STORY SO FAR (`lesson_day.v4.7`, EARLIER_DAYS; наряд GEN-3): what day N of a plan is told of the days before it — the
 * scene days whose lessons are written, in the calendar's order, each with its dialogue line by line, its frames in both
 * languages and its words — and in whose roles its lesson is spoken: the learner's of the plan, the partner's of the scene.
 */

/** A five-day plan (scene days 1, 2 and 4) built by the fake's blueprint, no lesson written yet. */
function ssPlan(): Plan
{
    $plan = Plan::create(
        id: PlanId::generate(),
        userId: UserId::generate(),
        goalText: 'Иду к врачу с ребёнком',
        targetLang: new LanguageCode('en'),
        nativeLang: new LanguageCode('ru'),
        level: PlanLevel::Beginner,
        daysRequested: 5,
        eventDate: null,
        today: new DateTimeImmutable('2026-09-17'),
        now: new DateTimeImmutable('2026-09-17T10:00:00Z'),
        dayIds: static fn (): PlanDayId => PlanDayId::generate(),
    );
    $request = new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, PlanCalendar::scenesCount(5));
    $plan->beginBuild(new DateTimeImmutable('2026-09-17T10:00:00Z'));
    $plan->acceptBlueprint((new BlueprintParser)->parse(FakePlanModel::planPayload($request)), new ModelCall('plan-builder-v2', 'test', 'fake', '0.000000', 1, 1), [], static fn (): PlanSceneId => PlanSceneId::generate());

    return $plan;
}

/** The scene of a day of the plan. */
function ssSceneOfDay(Plan $plan, int $number): PlanScene
{
    return $plan->sceneOf($plan->day($number)) ?? throw new RuntimeException("day {$number} holds no scene");
}

/** Write the fake's lesson for the day `$story` of the story into a scene, its partner imagined as `$gender`. */
function ssWrite(PlanScene $scene, int $story, VoiceGender $gender = VoiceGender::Female): void
{
    $earlier = new EarlierDays(array_fill(0, $story - 1, planEarlierDay()));
    $payload = FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), $earlier));
    $payload['role_gender'] = $gender->value;
    $scene->acceptLesson((new LessonParser)->parse($payload), lessonPacks()->for('en'), new ModelCall('lesson_day.v4.9', 'test', 'fake', '0.000000', 1, 1), [], new DateTimeImmutable('2026-09-17T10:00:00Z'));
}

// Наряд GEN-3, §2: «EARLIER_DAYS — все содержательные дни этого плана с готовым уроком, раньше текущего, по порядку; на первый
// день — none». Catches a first day told a story, a later day told a day AFTER it or a day whose lesson is not written, the
// days out of the calendar's order, a review day counted as a day of the story, and the partner's gender forgotten.
it('tells a scene day every earlier scene day whose lesson is written, in the order of the calendar, and the first day nothing', function () {
    $plan = ssPlan();
    ssWrite(ssSceneOfDay($plan, 1), 1, VoiceGender::Male);
    ssWrite(ssSceneOfDay($plan, 4), 3);

    $first = $plan->earlierDaysOf(ssSceneOfDay($plan, 1)->id());
    $second = $plan->earlierDaysOf(ssSceneOfDay($plan, 2)->id());
    $fourth = $plan->earlierDaysOf(ssSceneOfDay($plan, 4)->id());

    ssWrite(ssSceneOfDay($plan, 2), 2);
    $fourthAfterSecond = $plan->earlierDaysOf(ssSceneOfDay($plan, 4)->id());

    expect($first->isEmpty())->toBeTrue()
        ->and(array_map(static fn ($d): int => $d->number, $second->days))->toBe([1])
        // Day 2's lesson is not written yet: day 4 hears of day 1 only — and never of itself.
        ->and(array_map(static fn ($d): int => $d->number, $fourth->days))->toBe([1])
        ->and(array_map(static fn ($d): int => $d->number, $fourthAfterSecond->days))->toBe([1, 2])
        ->and($second->days[0]->titleTarget)->toBe(ssSceneOfDay($plan, 1)->titleTarget())
        ->and($second->days[0]->partnerRoleTarget)->toBe(ssSceneOfDay($plan, 1)->partnerRoleTarget())
        ->and($second->days[0]->partnerGender)->toBe(VoiceGender::Male);
});

// Наряд GEN-3, §2: «строки диалога по порядку, каждая «A: …» или «B: …», только text_target; Frames: {frame_target} = {frame_native};
// Words: {term_target}». Catches frames told in one language only (a native twin of an earlier frame would go unseen by the
// model), lines out of the visit's order or of one speaker, and words missing.
it('tells each earlier day its lines in the order of the visit, its frames in both languages and its words', function () {
    $plan = ssPlan();
    ssWrite(ssSceneOfDay($plan, 1), 1);

    $day = $plan->earlierDaysOf(ssSceneOfDay($plan, 2)->id())->days[0];

    expect(array_slice($day->lines, 0, 3))->toBe([
        ['speaker' => 'A', 'text' => 'Where does it hurt: his upper back or his lower back?'],
        ['speaker' => 'B', 'text' => 'It hurts in his lower back.'],
        ['speaker' => 'A', 'text' => 'Did it start today, or earlier this week?'],
    ])
        ->and(count($day->lines))->toBe(16)
        ->and($day->lines[10])->toBe(['speaker' => 'B', 'text' => 'Sorry, could you say that more slowly?'])
        ->and($day->frames[0])->toBe(['target' => 'It hurts in his ___.', 'native' => 'У него болит ___.'])
        ->and(count($day->frames))->toBe(6)
        ->and($day->words)->toBe(['lower back', 'sharp', 'fever', 'muscle strain', 'heating pad', 'X-ray', 'follow-up appointment', 'sick note']);
});

// Наряд GEN-3, §3: «learner_role и role_target / role_native каждого сообщения сервер перезаписывает из плана». Catches the
// scene's own learner role used instead of the plan's (day 2 naming the learner otherwise than day 1), the partner named by
// the model, and one message left in the model's role.
it('speaks a lesson in the plan\'s learner role and the scene\'s partner role, whatever the model wrote', function () {
    $plan = ssPlan();
    $scene = ssSceneOfDay($plan, 2);
    $roles = $plan->lessonRoles($scene);
    $payload = FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    $payload['learner_role'] = ['role_target' => 'Worried parent', 'role_native' => 'Взволнованный родитель'];
    $payload['dialogue'][2]['messages'][0]['role_target'] = 'Physician';

    $spoken = (new LessonParser)->parse($payload)->withRoles($roles);
    $said = [];
    foreach ($spoken->exchanges as $exchange) {
        foreach ($exchange->messages as $message) {
            $said[$message->speaker][] = "{$message->roleTarget} / {$message->roleNative}";
        }
    }

    expect($roles)->toEqual(new LessonRoles('Parent', 'Родитель', $scene->partnerRoleTarget(), $scene->partnerRoleNative()))
        ->and($scene->partnerRoleTarget())->toBe('Doctor')
        ->and([$spoken->learnerRoleTarget, $spoken->learnerRoleNative])->toBe(['Parent', 'Родитель'])
        ->and(array_unique($said['A']))->toBe(['Doctor / Врач'])
        ->and(array_unique($said['B']))->toBe(['Parent / Родитель']);
});

// A plan that has no titles has no learner role to speak a lesson in — it has no scenes either; asking is a state error,
// never a lesson spoken in an empty role.
it('refuses to name the roles of a lesson of a plan that has no titles', function () {
    $plan = Plan::create(
        id: PlanId::generate(), userId: UserId::generate(), goalText: 'x', targetLang: new LanguageCode('en'), nativeLang: new LanguageCode('ru'),
        level: PlanLevel::Beginner, daysRequested: 2, eventDate: null, today: new DateTimeImmutable('2026-09-17'),
        now: new DateTimeImmutable('2026-09-17T10:00:00Z'), dayIds: static fn (): PlanDayId => PlanDayId::generate(),
    );
    $scene = ssSceneOfDay(ssPlan(), 1);

    expect(fn () => $plan->lessonRoles($scene))->toThrow(PlanNotInState::class);
});
