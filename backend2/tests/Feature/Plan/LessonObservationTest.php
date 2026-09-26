<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
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
 * THE LESSON CALL OF `lesson_day.v4.5` OVER HTTP (наряды GEN-2a, GEN-2b): warnings are counted and the day comes out (the
 * fatal codes — `LessonGateBuildTest`); a refusal is only an answer off the schema; the inputs are the prompt's —
 * the learner's gender from the profile at the moment the day is written, the learner's own words beside the
 * brief, no count of frames.
 */

// Canon: «остальные — предупреждения» (день ВЫХОДИТ, нарушения считаются). Catches a warning that refuses the lesson,
// buys a second call or a repair, or leaves the day unopenable — and findings that lose their card's address. A frame
// written without its full stop (доработка GEN-2b) is such a warning: its line is still read against it.
it('counts what a lesson breaks and gives the day all the same', function () {
    [, $token] = planLearner();
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = planCleanLesson($request);
        $p['phrases'][1]['frame_target'] = 'It started ___';
        $p['dialogue'][1]['check']['options'][1]['text_target'] = 'Earlier this week';
        $p['phrases'][4]['frame_native'] = 'Он будет отдыхать в/на ___.';

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);

    $build = planCreate($this, $token, ['days_total' => 1]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    $day = planOpenDay($this, $token, $build['id'], 1);

    $findings = json_decode((string) DB::table('plan_scenes')->where('plan_id', $build['id'])->value('checks_json'), true);
    expect($fake->lessonCalls)->toBe(1)
        ->and($fake->repairCalls)->toBe(0)
        ->and(planRead($this, $token, $build['id'])['scenes'][0]['lesson_status'])->toBe('ready')
        ->and(count($day['cards']))->toBeGreaterThan(60)
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", $findings))
        ->toEqualCanonicalizing(['frame.native_alternatives@p5', 'check.verbatim@x2.check', 'frame.no_end_punct@p2']);

    [, $admin] = adminActor();
    $rows = $this->withHeader('Authorization', "Bearer {$admin}")->getJson('/admin/api/plans/checks')->assertOk()->json('data');
    expect(array_values(array_filter($rows, static fn (array $r): bool => $r['prompt_version'] === 'lesson_day.v4.10')))->toEqualCanonicalizing([
        ['prompt_version' => 'lesson_day.v4.10', 'check' => 'frame.native_alternatives', 'action' => 'counted', 'hits' => 1],
        ['prompt_version' => 'lesson_day.v4.10', 'check' => 'check.verbatim', 'action' => 'counted', 'hits' => 1],
        ['prompt_version' => 'lesson_day.v4.10', 'check' => 'frame.no_end_punct', 'action' => 'counted', 'hits' => 1],
    ]);
});

// Canon (docs/plan-v2.md §2): one retry, and only for an answer that is not the schema; the second refusal fails the
// lesson, and only the learner's explicit retry buys a third call.
it('asks once more only for an answer off the schema, fails the lesson on the second, and lets the learner retry it', function () {
    [, $token] = planLearner();
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = planCleanLesson($request);
        unset($p['listening']);

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);

    $build = planCreate($this, $token, ['days_total' => 1]);
    $plan = planRead($this, $token, $build['id']);
    expect($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toContain('listening')
        ->and($fake->lessonCalls)->toBe(2)
        ->and(DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.10')->count())->toBe(0);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/scenes/{$plan['scenes'][0]['id']}/lesson/retry")->assertStatus(202);
    expect($fake->lessonCalls)->toBe(4);
});

// Canon: «LEARNER_GENDER из профиля, unknown по умолчанию; факты о ученике из цели плана — в TOPIC_DESCRIPTION»; the
// inputs are exactly the prompt's INPUTS (the model takes the number of frames from the dialogue; v4.6 adds the roles and
// EARLIER_DAYS — наряд GEN-3). Catches a gender read once and cached, the learner's facts left out of the brief, an input
// the prompt does not name.
it('writes a lesson with the learner\'s gender as the profile says it now and the learner\'s own words beside the brief', function () {
    [$user, $token] = planLearner();
    $fake = new FakePlanModel;
    app()->instance(PlanModelPort::class, $fake);

    planCreate($this, $token, ['days_total' => 2, 'goal_text' => 'Собеседование в пятницу. У меня пять лет опыта в продажах']);
    DB::table('profiles')->where('user_id', $user->id)->update(['gender' => 'female']);
    $plan = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();
    planWalkDay($this, $token, $plan['id'], 1);

    [$first, $second] = $fake->lessonRequests;
    $prompts = new PlanPromptFiles(app_path('Modules/Plan/Infrastructure/Prompt'));
    $user = $prompts->lessonUser($first);

    expect($first->learnerGender)->toBeNull()
        ->and($second->learnerGender)->toBe(VoiceGender::Female)
        ->and($first->topicDescription)->toEndWith("\n\nAbout the learner, in their own words: Собеседование в пятницу. У меня пять лет опыта в продажах")
        ->and($user)->toContain("LEARNER_GENDER: unknown\n")
        ->and(array_values(array_map(static fn (string $line): string => explode(':', $line, 2)[0], preg_grep('/^[A-Z_]+: /', explode("\n", $user)) ?: [])))
        ->toBe(['TOPIC', 'TOPIC_DESCRIPTION', 'TARGET_LANGUAGE', 'NATIVE_LANGUAGE', 'LEVEL', 'LEARNER_GENDER', 'LEARNER_ROLE', 'PARTNER_ROLE', 'VOCABULARY_COUNT', 'DIALOGUE_COUNT'])
        ->and($prompts->lessonUser($second))->toContain('LEARNER_GENDER: female')
        ->and($prompts->lessonVersion())->toBe('lesson_day.v4.10')
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

// P2R quotes the lesson prompt's own sections — of the prompt the day is written with now, v4.10: a repair never retells
// a rule. Catches a wrapper quoting a prompt that is no longer the lesson's, an exchange repaired without the rules
// of a turn of the visit, and (наряд GEN-3) a frame, an exchange or a word repaired without the story so far.
it('gives a card repair the lesson prompt\'s own sections for that card, word for word', function () {
    $prompts = new PlanPromptFiles(app_path('Modules/Plan/Infrastructure/Prompt'));
    $lesson = (string) file_get_contents(app_path('Modules/Plan/Infrastructure/Prompt/lesson_day.v4.10.md'));

    expect($prompts->repairVersion())->toBe('lesson_card_repair.v1.3')
        ->and($prompts->lessonVersion())->toBe('lesson_day.v4.10')
        ->and($lesson)->toContain($prompts->lessonSection('CHECK PER EXCHANGE'))
        ->and($prompts->repairSystem('check'))->toContain($prompts->lessonSection('CHECK PER EXCHANGE'))
        ->and($prompts->repairSystem('frame'))->toContain($prompts->lessonSection('FRAMES'))
        // v4.5's own words, not v4.4's: the slot cut per language is in the quoted FRAMES.
        ->and($prompts->repairSystem('frame'))->toContain('WHERE THE SLOT CUTS')
        ->and($prompts->repairSystem('frame'))->not->toContain('{{rules}}')
        ->and($prompts->repairSystem('exchange'))->toContain($prompts->lessonSection('NATURAL ORDER OF ONE VISIT'))
        ->and($prompts->repairSystem('exchange'))->toContain($prompts->lessonSection('CONVERSATION PARTNER RULE'))
        ->and($prompts->repairSystem('exchange'))->toContain($prompts->lessonSection('CHECK PER EXCHANGE'))
        ->and($prompts->repairSystem('listening'))->not->toContain('CHECK PER EXCHANGE')
        ->and($prompts->repairSystem('frame'))->toContain($prompts->lessonSection('THE STORY SO FAR'))
        ->and($prompts->repairSystem('exchange'))->toContain($prompts->lessonSection('THE STORY SO FAR'))
        ->and($prompts->repairSystem('term'))->toContain($prompts->lessonSection('THE STORY SO FAR'))
        ->and($prompts->repairSystem('term'))->toContain($prompts->lessonSection('VOCABULARY'))
        ->and($prompts->repairSystem('check'))->not->toContain('THE STORY SO FAR');
});

// Canon GEN-2b: «filler.native_seam — собранная фраза на родном не читается: ОДИН вызов дешёвой модели-судьи на день, все
// собранные пары списком, ответ «да/нет» на каждую; правило языка не кодируется». Catches a judge asked per frame or
// per repair (a day with two repaired cards paying three judges), a pair left out of the one call, a «no» that is not a
// finding at its filler — and a judge's cost lost from the lesson.
it('asks the seam judge once a day with every native sentence of the lesson, and counts what does not read', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            $p['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';
            array_pop($p['dialogue'][1]['check']['options']);

            return $p;
        },
        repair: static function ($request): array {
            $card = $request->card;
            if ($request->kind === 'frame') {
                $card['slot']['fillers'][1]['target'] = 'neck';
            } else {
                $card['options'][] = ['text_target' => 'Next year', 'text_native' => 'В следующем году'];
            }

            return ['card' => $card];
        },
        judge: static fn ($request): array => ['verdicts' => array_map(
            static fn (string $id): array => ['id' => $id, 'reads' => $id !== 'p1.f2'],
            $request->ids(),
        )],
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();
    $sentences = array_column($fake->judgeRequests[0]->items, 'sentence', 'id');

    expect($fake->repairCalls)->toBe(2)
        ->and($fake->judgeCalls)->toBe(1)
        ->and($fake->judgeRequests[0]->nativeLanguage)->toBe('Russian')
        // Every filler of every native frame with a slot — 5 frames × 3 fillers — in the one call.
        ->and(count($sentences))->toBe(15)
        ->and($sentences['p1.f1'])->toBe('У него болит поясница.')
        ->and($sentences['p6.f1'])->toBe('Нам нужно сделать рентген?')
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", json_decode((string) $scene->checks_json, true)))->toBe(['filler.native_seam@p1.f2'])
        // Lesson 7 ms, two repairs 3 ms each, the judge 2 ms.
        ->and((int) $scene->latency_ms_lesson)->toBe(7 + 3 + 3 + 2)
        ->and((int) DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.10')->where('check_name', 'filler.native_seam')->value('hits'))->toBe(1);
});

// Canon GEN-2b: «код, для которого пакета нет, — пропуск проверки со счётчиком lang.pack_missing, НЕ находка». A learner
// whose language has only the skeleton of a pack (ro). Catches a lesson for that learner judged by another language's
// words, a skip written as a finding or holding the day, and a skip nobody counts.
it('builds a day for a learner whose language has no rules yet, skipping and counting what it cannot check', function () {
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => 'ro']);
    // Since наряд LANG-1 every plan native has a pack and a native outside the list is refused (422
    // language_pair_invalid), so «a language with no rules yet» is only reachable by leaving its pack out of the
    // registry — the case this canon keeps: a pack that is missing is skipped and counted, never guessed at.
    $packs = (array) config('lesson.lang');
    unset($packs['ro']);
    app()->instance(LanguagePacks::class, new LanguagePacks($packs));
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = planCleanLesson($request);
        $p['vocabulary'][1]['pronunciation_native'] = 'șarp';
        $p['dialogue'][0]['messages'][1]['text_native'] = 'Я заметил, что у него болит поясница.';

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $findings = json_decode((string) DB::table('plan_scenes')->where('plan_id', $id)->value('checks_json'), true);
    $counters = DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.10')->pluck('hits', 'check_name')->all();

    expect($fake->lessonRequests[0]->nativeLanguage)->toBe('Romanian')
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and($findings)->toBe([])
        // Ten checks read the learner's language: seven of GEN-2b, the native side of `frame.no_end_punct`, the letters
        // of a reading (наряд BACK-TAILS-1 §3.2), and the numbers, times and names a piece of the partner's line is read
        // by (`options.partner_fragment`, наряд LANG-1b §1).
        ->and($counters)->toBe(['lang.pack_missing' => 10])
        ->and($fake->repairCalls)->toBe(0);
});
