<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * DAY N KNOWS THE DAYS BEFORE IT (наряд GEN-3; since GEN-4 in two stages) — through the build as production runs it: the next
 * day's skeleton is asked with the story so far and the day is spoken in the plan's roles; a frame an earlier day taught asks
 * the skeleton once more (`frame.known_repeat`, fatal), and a repair of the day is told the story too.
 */

/** A two-day plan of the given fake, started, day 1 walked — day 2's lesson written by then. */
function sbTwoDays(object $ctx, FakePlanModel $fake): array
{
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planWalkDay($ctx, $token, $id, 1);
    $scenes = DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->get()->all();

    return [$token, $id, $scenes];
}

// Наряд GEN-3, §0 and §2: «причина одна: промт дня получает только описание своей сцены и не знает ни материала, ни фактов прошлых
// дней»; §3: «роли сервер перезаписывает из плана». Catches a day 2 asked without day 1's lines, frames and words, a day 1 asked
// with a story, and a lesson stored in the roles the model named — the strip of the scene and the bubbles saying two names.
it('asks day 2 with day 1\'s lines, frames and words, and stores both days in the plan\'s roles', function () {
    $fake = new FakePlanModel(lesson: static function (LessonRequest $request): array {
        $p = planCleanLesson($request);
        $p['learner_role'] = ['role_target' => 'Worried parent', 'role_native' => 'Встревоженный родитель'];

        return $p;
    });
    [, , $scenes] = sbTwoDays($this, $fake);
    [$dayOne, $dayTwo] = $fake->skeletonRequests;
    $roles = static function (object $scene): array {
        $lesson = json_decode((string) $scene->lesson_json, true);
        $said = ['learner_role' => $lesson['learner_role']['role_target']];
        foreach ($lesson['dialogue'] as $exchange) {
            foreach ($exchange['messages'] as $message) {
                $said[$message['speaker']][$message['role_target'].' / '.$message['role_native']] = true;
            }
        }

        return [$said['learner_role'], array_keys($said['A']), array_keys($said['B'])];
    };

    expect($dayOne->earlierDays->isEmpty())->toBeTrue()
        ->and(array_map(static fn ($d): int => $d->number, $dayTwo->earlierDays->days))->toBe([1])
        ->and($dayTwo->earlierDays->days[0]->words)->toContain('lower back', 'sharp')
        ->and($dayTwo->earlierDays->days[0]->frames[0])->toBe(['target' => 'It hurts in his ___.', 'native' => 'У него болит ___.'])
        ->and($dayTwo->earlierDays->days[0]->lines[1])->toBe(['speaker' => 'B', 'text' => 'It hurts in his lower back.'])
        ->and([$dayOne->roles->learnerTarget, $dayOne->roles->partnerTarget, $dayTwo->roles->partnerTarget])->toBe(['Parent', 'Receptionist', 'Doctor'])
        ->and($roles($scenes[0]))->toBe(['Parent', ['Receptionist / Регистратор'], ['Parent / Родитель']])
        ->and($roles($scenes[1]))->toBe(['Parent', ['Doctor / Врач'], ['Parent / Родитель']])
        ->and(json_decode((string) $scenes[1]->checks_json, true))->toBe([])
        ->and($scenes[1]->lesson_status)->toBe('ready');
});

// Наряд GEN-4, 3.3: «каркас равен Frame из EARLIER_DAYS (строковое равенство в любом языке)» — fatal, the stage is asked once
// more with the finding quoted; a repeat that says it again fails the day, and no dialogue is paid for. Catches a day 2 dealt
// with a frame day 1 taught, a repeat asked without the reason, and a dialogue written over a skeleton the check refused.
it('asks day 2\'s skeleton again for a frame day 1 taught, and fails the day when the repeat says it too', function (bool $again) {
    $fake = new FakePlanModel(skeleton: static function (LessonRequest $request) use ($again): array {
        $skeleton = FakePlanModel::skeletonPayload($request);
        if (! $request->earlierDays->isEmpty() && ($again || $request->previousViolations === [])) {
            $skeleton['phrases'][0]['frame_target'] = 'It hurts in his ___';
        }

        return $skeleton;
    });
    [, , $scenes] = sbTwoDays($this, $fake);
    $asked = $fake->skeletonRequests;

    expect($fake->skeletonCalls)->toBe(3)
        ->and($asked[1]->previousViolations)->toBe([])
        ->and($asked[2]->previousViolations)->toHaveCount(1)
        ->and($asked[2]->previousViolations[0])->toStartWith('frame.known_repeat · p1: ')
        ->and($scenes[1]->lesson_status)->toBe($again ? 'failed' : 'ready')
        ->and($scenes[1]->fail_reason)->toBe($again ? 'fatal: frame.known_repeat' : null)
        // Day 1's dialogue, and day 2's only over a skeleton the check let through.
        ->and($fake->dialogueCalls)->toBe($again ? 1 : 2);
})->with(['the repeat is clean' => [false], 'the repeat says it again' => [true]]);

// Наряд GEN-4, 3.9: «вход … EARLIER_DAYS». Catches a repair of a later day asked without the story so far — the card
// written again into a word or a frame an earlier day taught — and a skeleton card sent with a dialogue it does not have yet.
it('tells a repair of day 2 the story so far', function () {
    $fake = new FakePlanModel(lesson: static function (LessonRequest $request): array {
        $p = planCleanLesson($request);
        if (! $request->earlierDays->isEmpty()) {
            // A partner line naming a filler of the frame it pairs with — a warning, its card sent to a repair.
            $p['dialogue'][2]['messages'][0]['text_target'] = 'Is the pain dull, or more like a slow ache?';
        }

        return $p;
    });
    [, , $scenes] = sbTwoDays($this, $fake);
    $repair = $fake->repairRequests[0] ?? null;

    expect($fake->repairCalls)->toBe(1)
        ->and($repair?->kind)->toBe('partner_line')
        ->and($repair?->address)->toBe('a3')
        ->and(array_column($repair->findings ?? [], 'code'))->toBe(['partner.names_filler'])
        ->and($repair?->earlierDays->days[0]->words)->toContain('sharp')
        ->and($repair?->dialogue)->toBeNull()
        ->and($repair?->neighbours)->toBeNull()
        // The fake's repair gives the card back as it was: the warning stays, stored with the day, and the day is dealt.
        ->and(array_column(json_decode((string) $scenes[1]->checks_json, true), 'code'))->toBe(['partner.names_filler'])
        ->and($scenes[1]->lesson_status)->toBe('ready');
});
