<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE LESSON CALL OF `lesson_day.v4.4` OVER HTTP (наряд GEN-2a): the validator observes and the day comes out;
 * a refusal is only an answer off the schema; the inputs are the prompt's — the learner's gender from the
 * profile at the moment the day is written, the learner's own words beside the brief, no count of frames.
 */

// Canon: «Валидатор — режим наблюдения (день ВЫХОДИТ, нарушения считаются)». Catches a finding that refuses the lesson,
// buys a second call, or leaves the day unopenable — and findings that lose their card's address.
it('counts what a lesson breaks and gives the day all the same', function () {
    [, $token] = planLearner();
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = FakePlanModel::lessonPayload($request);
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'in his';
        $p['dialogue'][1]['messages'][1]['text_target'] = 'It began three days ago.';
        $p['phrases'][4]['frame_native'] = 'Он будет отдыхать в/на ___.';

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);

    $build = planCreate($this, $token, ['days_total' => 1]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    $day = planOpenDay($this, $token, $build['id'], 1);

    $findings = json_decode((string) DB::table('plan_scenes')->where('plan_id', $build['id'])->value('checks_json'), true);
    expect($fake->lessonCalls)->toBe(1)
        ->and(planRead($this, $token, $build['id'])['scenes'][0]['lesson_status'])->toBe('ready')
        ->and(count($day['cards']))->toBeGreaterThan(60)
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", $findings))
        ->toEqualCanonicalizing(['frame.native_alternatives@p5', 'line.ne_frame@B2', 'key.no_content_word@B1']);

    [, $admin] = adminActor();
    $rows = $this->withHeader('Authorization', "Bearer {$admin}")->getJson('/admin/api/plans/checks')->assertOk()->json('data');
    expect(array_values(array_filter($rows, static fn (array $r): bool => $r['prompt_version'] === 'lesson_day.v4.4')))->toEqualCanonicalizing([
        ['prompt_version' => 'lesson_day.v4.4', 'check' => 'frame.native_alternatives', 'action' => 'counted', 'hits' => 1],
        ['prompt_version' => 'lesson_day.v4.4', 'check' => 'line.ne_frame', 'action' => 'counted', 'hits' => 1],
        ['prompt_version' => 'lesson_day.v4.4', 'check' => 'key.no_content_word', 'action' => 'counted', 'hits' => 1],
    ]);
});

// Canon (docs/plan-v2.md §2): one retry, and only for an answer that is not the schema; the second refusal fails the
// lesson, and only the learner's explicit retry buys a third call.
it('asks once more only for an answer off the schema, fails the lesson on the second, and lets the learner retry it', function () {
    [, $token] = planLearner();
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = FakePlanModel::lessonPayload($request);
        unset($p['listening']);

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);

    $build = planCreate($this, $token, ['days_total' => 1]);
    $plan = planRead($this, $token, $build['id']);
    expect($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toContain('listening')
        ->and($fake->lessonCalls)->toBe(2)
        ->and(DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.4')->count())->toBe(0);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/scenes/{$plan['scenes'][0]['id']}/lesson/retry")->assertStatus(202);
    expect($fake->lessonCalls)->toBe(4);
});

// Canon: «LEARNER_GENDER из профиля, unknown по умолчанию; факты о ученике из цели плана — в TOPIC_DESCRIPTION; PHRASES_COUNT
// из входов убран» (v4.4). Catches a gender read once and cached, the learner's facts left out of the brief, a count of
// frames sent to a prompt that no longer has one.
it('writes a lesson with the learner\'s gender as the profile says it now and the learner\'s own words beside the brief', function () {
    [$user, $token] = planLearner();
    $fake = new FakePlanModel;
    app()->instance(PlanModelPort::class, $fake);

    planCreate($this, $token, ['days_total' => 2, 'goal_text' => 'Собеседование в пятницу. У меня пять лет опыта в продажах']);
    DB::table('profiles')->where('user_id', $user->id)->update(['gender' => 'female']);
    $plan = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();
    planOpenDay($this, $token, $plan['id'], 1);

    [$first, $second] = $fake->lessonRequests;
    $prompts = new PlanPromptFiles(app_path('Modules/Plan/Infrastructure/Prompt'));
    $user = $prompts->lessonUser($first);

    expect($first->learnerGender)->toBeNull()
        ->and($second->learnerGender)->toBe(VoiceGender::Female)
        ->and($first->topicDescription)->toEndWith("\n\nAbout the learner, in their own words: Собеседование в пятницу. У меня пять лет опыта в продажах")
        ->and($user)->toContain("LEARNER_GENDER: unknown\n")
        ->and($user)->not->toContain('PHRASES_COUNT')
        ->and($prompts->lessonUser($second))->toContain('LEARNER_GENDER: female')
        ->and($prompts->lessonVersion())->toBe('lesson_day.v4.4')
        ->and($prompts->lessonSystem())->not->toContain('TEST INPUT')
        ->and($prompts->lessonSystem())->toContain('FINAL OUTPUT RULE');
});

// The schema holds every reference the vendor can hold, and no list length (п. 202).
it('asks with a strict schema whose references are enums and whose lists have no length', function () {
    $schema = PlanSchemas::lesson(8, 8);
    $learner = $schema['properties']['dialogue']['items']['properties']['messages']['items']['anyOf'][1];
    $json = (string) json_encode($schema);

    expect($learner['properties']['phrase_id'])->toBe(['type' => ['string', 'null'], 'enum' => ['p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', null]])
        ->and($schema['properties']['phrases']['items']['properties']['slot']['type'])->toBe(['object', 'null'])
        ->and($schema['properties']['phrases']['items']['properties']['slot']['properties']['fillers']['items']['properties']['in_dialogue'])->toBe(['type' => 'boolean'])
        ->and($schema['properties']['vocabulary']['items']['properties']['used_in']['items']['enum'])->toContain('A8')
        ->and(array_keys($schema['properties']))->toBe(['topic', 'learner_role', 'role_gender', 'dialogue', 'phrases', 'listening', 'vocabulary'])
        ->and($json)->not->toContain('minItems')
        ->and($json)->not->toContain('maxItems');
});

// P2R quotes the lesson prompt's own sections: a repair never retells a rule.
it('gives a card repair the lesson prompt\'s own sections for that card, word for word', function () {
    $prompts = new PlanPromptFiles(app_path('Modules/Plan/Infrastructure/Prompt'));
    $lesson = (string) file_get_contents(app_path('Modules/Plan/Infrastructure/Prompt/lesson_day.v4.4.md'));

    expect($prompts->repairVersion())->toBe('lesson_card_repair.v1')
        ->and($lesson)->toContain($prompts->lessonSection('CHECK PER EXCHANGE'))
        ->and($prompts->repairSystem('check'))->toContain($prompts->lessonSection('CHECK PER EXCHANGE'))
        ->and($prompts->repairSystem('frame'))->toContain($prompts->lessonSection('FRAMES'))
        ->and($prompts->repairSystem('frame'))->not->toContain('{{rules}}')
        ->and($prompts->repairSystem('listening'))->not->toContain('CHECK PER EXCHANGE');
});
