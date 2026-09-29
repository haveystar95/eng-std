<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\OptionShuffle;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE DAY, BUILT IN TWO STAGES (наряд GEN-4, §3) — through the build as production runs it: skeleton → its check → the seam
 * judge → the skeleton's repairs → the dialogue over the repaired skeleton → its check → the options shuffled → the dialogue's
 * repairs → the lesson. A fatal finding asks its stage once more, and a second fails the day; a warning sends its card to a
 * repair, two cards a stage at most, and a repair is kept only when it brings no fatal finding.
 */

/**
 * A one-day plan built by `$fake` — its day 1 written at once — and the scene's row.
 *
 * @return array{0: string, 1: string, 2: object}
 */
function dbBuild(object $ctx, FakePlanModel $fake): array
{
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 1])['id'];

    return [$token, $id, dbScene($id)];
}

function dbScene(string $planId): object
{
    return DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->first() ?? throw new LogicException('no scene');
}

/** @return list<string> the findings stored with the day, as `code@address` */
function dbFound(object $scene): array
{
    return array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", json_decode((string) $scene->checks_json, true) ?? []);
}

/** @return array<string, int> action → hits of one code under one prompt version */
function dbCounted(string $version, string $code): array
{
    return array_map('intval', DB::table('plan_check_counters')->where('prompt_version', $version)->where('check_name', $code)->pluck('hits', 'action')->all());
}

// Наряд GEN-4, 3: «один запрос дня = один скелет + один диалог»; 3.2: «скелет хранится рядом с уроком»; 3.8: «must_say,
// must_understand, partner_line, pairs_with — внутренние». Catches a clean day that pays for a repair or a second call, a
// skeleton not stored, DIALOGUE_COUNT counted otherwise, and the stages' own fields leaking into the lesson the client reads.
it('builds a clean day of one skeleton, one seam judge and one dialogue, and stores the skeleton beside the lesson', function () {
    $fake = new FakePlanModel;
    [, , $scene] = dbBuild($this, $fake);
    $skeleton = json_decode((string) $scene->skeleton_json, true);
    $lesson = (string) $scene->lesson_json;

    expect([$fake->skeletonCalls, $fake->judgeCalls, $fake->dialogueCalls, $fake->repairCalls])->toBe([1, 1, 1, 0])
        ->and($scene->lesson_status)->toBe('ready')
        ->and(dbFound($scene))->toBe([])
        ->and($scene->prompt_version_lesson)->toBe('lesson_skeleton.v1+lesson_dialogue.v1')
        ->and(array_column($skeleton['phrases'], 'must_say'))->toBe([[1], [2], [3], [4], [5], [6, 7]])
        ->and(array_column($skeleton['partner_lines'], 'id'))->toBe(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7'])
        ->and($fake->dialogueRequests[0]->dialogueCount)->toBe(8)
        // The seam judge reads every native frame with a window, each filler once — before the dialogue exists.
        ->and($fake->judgeRequests[0]->ids())->toHaveCount(15)
        ->and($lesson)->not->toContain('must_say')->not->toContain('must_understand')->not->toContain('partner_line')->not->toContain('pairs_with')
        ->and(DB::table('plan_check_counters')->whereIn('prompt_version', ['lesson_skeleton.v1', 'lesson_dialogue.v1'])->count())->toBe(0);
});

// Наряд GEN-4, 3: «повторный вызов ступени только по фатальной находке, не более одного повтора на ступень, потом ошибка
// сборки». Catches a fatal skeleton dealt, a repeat asked without the reason, a third call, a dialogue paid for over a skeleton
// the check refused, and a failure nobody counts.
it('asks the skeleton once more for a fatal finding, quoting it, and fails the day when the repeat has it too', function (bool $again) {
    $fake = new FakePlanModel(skeleton: static function (LessonRequest $request, int $call) use ($again): array {
        $skeleton = FakePlanModel::skeletonPayload($request);
        if ($call === 1 || $again) {
            $skeleton['phrases'][1]['must_say'] = [9];
        }

        return $skeleton;
    });
    [, , $scene] = dbBuild($this, $fake);

    expect($fake->skeletonCalls)->toBe(2)
        ->and($fake->skeletonRequests[0]->previousViolations)->toBe([])
        ->and($fake->skeletonRequests[1]->previousViolations)->toHaveCount(1)
        ->and($fake->skeletonRequests[1]->previousViolations[0])->toStartWith('frame.must_say · p2: ')
        ->and($fake->dialogueCalls)->toBe($again ? 0 : 1)
        ->and($scene->lesson_status)->toBe($again ? 'failed' : 'ready')
        ->and($scene->fail_reason)->toBe($again ? 'fatal: frame.must_say' : null)
        ->and(dbCounted('lesson_skeleton.v1', 'frame.must_say'))->toEqualCanonicalizing($again ? ['counted' => 2, 'gated' => 2, 'failed' => 1] : ['counted' => 1, 'gated' => 1]);
    if ($again) {
        expect(dbFound($scene))->toContain('frame.must_say@p2');
    }
})->with(['the repeat is clean' => [false], 'the repeat is fatal again' => [true]]);

// The same for the dialogue (3.6: «реплика A из скелета … отличается хоть символом» — fatal). Catches a dialogue that retells a
// partner line dealt, a repeat of the skeleton bought for the dialogue's fault, and a day built on the second refusal.
it('asks the dialogue once more for a fatal finding, and fails the day when the repeat has it too', function (bool $again) {
    $fake = new FakePlanModel(dialogue: static function (DialogueRequest $request, int $call) use ($again): array {
        $dialogue = FakePlanModel::dialoguePayload($request);
        if ($call === 1 || $again) {
            $dialogue['dialogue'][2]['messages'][0]['text_target'] = 'Is the pain bad?';
        }

        return $dialogue;
    });
    [, , $scene] = dbBuild($this, $fake);

    expect([$fake->skeletonCalls, $fake->dialogueCalls])->toBe([1, 2])
        ->and($fake->dialogueRequests[1]->previousViolations[0] ?? null)->toStartWith('partner.changed · A3: ')
        ->and($scene->lesson_status)->toBe($again ? 'failed' : 'ready')
        ->and($scene->fail_reason)->toBe($again ? 'fatal: partner.changed' : null)
        ->and(dbCounted('lesson_dialogue.v1', 'partner.changed'))->toEqualCanonicalizing($again ? ['counted' => 2, 'gated' => 2, 'failed' => 1] : ['counted' => 1, 'gated' => 1]);
})->with(['the repeat is clean' => [false], 'the repeat is fatal again' => [true]]);

// Canon (docs/plan-v2.md §2): an answer off the schema is asked once more; the second fails the day, and only the learner's
// retry buys a new build. Catches an off-shape answer dealt or counted as a finding, a third call, and a failed day that
// cannot be asked again.
it('asks a stage once more for an answer off the schema, fails the day on the second, and lets the learner retry it', function () {
    $fake = new FakePlanModel(skeleton: static fn (LessonRequest $request, int $call): array => $call <= 2 ? ['phrases' => 'none'] : FakePlanModel::skeletonPayload($request));
    [$token, $id, $scene] = dbBuild($this, $fake);

    expect($fake->skeletonCalls)->toBe(2)
        ->and($fake->dialogueCalls)->toBe(0)
        ->and($fake->skeletonRequests[1]->previousViolations)->toHaveCount(1)
        ->and($scene->lesson_status)->toBe('failed')
        ->and($scene->fail_reason)->toBe($fake->skeletonRequests[1]->previousViolations[0])
        ->and(DB::table('plan_check_counters')->where('prompt_version', 'lesson_skeleton.v1')->count())->toBe(0);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/scenes/{$scene->id}/lesson/retry")->assertStatus(202);
    expect($fake->skeletonCalls)->toBe(3)
        ->and(dbScene($id)->lesson_status)->toBe('ready');
});

/** The fake's clean day with a warning on each of three cards of the skeleton — a frame, a partner line, a word. */
function dbThreeWarnings(LessonRequest $request): array
{
    $p = planCleanLesson($request);
    // p2: eight words before its window (`frame.too_long`), said so in its exchange.
    $p['phrases'][1]['frame_target'] = 'It started, as far as I can tell, ___.';
    $p['dialogue'][1]['messages'][1]['text_target'] = 'It started, as far as I can tell, three days ago.';
    // a1 names the filler of the frame it pairs with (`partner.names_filler`).
    $p['dialogue'][0]['messages'][0]['text_target'] = 'Is it his lower back that hurts?';
    // v2 defined in the learner's language (`vocab.definition_language`).
    $p['vocabulary'][1]['definition_target'] = 'резкая и сильная';

    return $p;
}

// Наряд GEN-4, 3.9: «починка v1.5 карточек frame/term/partner_line на скелете»; «после починки partner_line код заменяет реплику
// A»; 3.4: the seam judge reads the repaired frames. Catches a third card repaired, cards repaired in another order than
// frames first, a repair kept that the dialogue then says the old way, and a repaired frame never read by the judge again.
it('sends two cards of the skeleton\'s warnings to repairs, frames first, and writes the dialogue over the repaired skeleton', function () {
    $fake = new FakePlanModel(
        lesson: dbThreeWarnings(...),
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            if ($request->address === 'p2') {
                $card['frame_target'] = 'It started ___.';
            }
            if ($request->address === 'a1') {
                $card['text_target'] = 'Where does it hurt: in his upper back or lower down?';
            }

            return ['card' => $card];
        },
    );
    [, , $scene] = dbBuild($this, $fake);
    $lesson = json_decode((string) $scene->lesson_json, true);

    expect(array_map(static fn (LessonCardRepairRequest $r): string => "{$r->kind}:{$r->address}", $fake->repairRequests))->toBe(['frame:p2', 'partner_line:a1'])
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['frame.too_long'])
        ->and($fake->repairRequests[1]->dialogue)->toBeNull()
        ->and($scene->lesson_status)->toBe('ready')
        // The word was the third card: its warning stays with the day.
        ->and(dbFound($scene))->toBe(['vocab.definition_language@v2'])
        ->and($fake->dialogueRequests[0]->skeleton->frame('p2')?->phrase->frameTarget)->toBe('It started ___.')
        ->and($lesson['phrases'][1]['frame_target'])->toBe('It started ___.')
        ->and($lesson['dialogue'][1]['messages'][1]['text_target'])->toBe('It started three days ago.')
        ->and($lesson['dialogue'][0]['messages'][0]['text_target'])->toBe('Where does it hurt: in his upper back or lower down?')
        ->and($fake->judgeCalls)->toBe(2)
        ->and($fake->judgeRequests[1]->ids())->toBe(['p2.f1', 'p2.f2', 'p2.f3']);
});

// «Repair kept only if no new fatal»: a repair that breaks what the check holds is thrown away, the card as the stage wrote it
// kept with its warning. Catches a repair written into the day unchecked — here a Latin «o» inside a Russian word
// (`pronunciation.foreign_script`, fatal) — and a day failed over a repair the build could have done without.
it('keeps the card as written when its repair brings a fatal finding', function () {
    $fake = new FakePlanModel(
        lesson: static function (LessonRequest $request): array {
            $p = planCleanLesson($request);
            $p['dialogue'][0]['messages'][0]['text_target'] = 'Is it his lower back that hurts?';

            return $p;
        },
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => [...$request->card, 'text_target' => 'Where does it hurt?', 'text_native' => 'Где бoлит?']],
    );
    [, , $scene] = dbBuild($this, $fake);
    $lesson = json_decode((string) $scene->lesson_json, true);

    expect($fake->repairCalls)->toBe(1)
        ->and($scene->lesson_status)->toBe('ready')
        ->and(dbFound($scene))->toBe(['partner.names_filler@a1'])
        ->and($lesson['dialogue'][0]['messages'][0]['text_target'])->toBe('Is it his lower back that hurts?');
});

// Наряд GEN-4, 3.4: «судья швов по родным каркасам скелета до диалога; false → карточка каркаса в починку». Catches a judge asked
// after the dialogue, a «does not read» that is no finding at its filler or sends no frame to a repair, and a repaired frame
// whose seams nobody reads again.
it('sends a frame the seam judge cannot read to a repair, and has the judge read that frame alone again', function () {
    $fake = new FakePlanModel(
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            $card['slot']['fillers'][1]['pronunciation_native'] = 'нек';

            return ['card' => $card];
        },
        judge: static fn (NativeSeamJudgeRequest $request, int $call): array => ['verdicts' => array_map(
            static fn (string $id): array => ['id' => $id, 'reads' => ! ($call === 1 && $id === 'p1.f2')],
            $request->ids(),
        )],
    );
    [, , $scene] = dbBuild($this, $fake);

    expect($fake->repairRequests[0]->address ?? null)->toBe('p1')
        ->and(array_column($fake->repairRequests[0]->findings ?? [], 'code'))->toBe(['filler.native_seam'])
        ->and($fake->judgeCalls)->toBe(2)
        ->and($fake->judgeRequests[1]->ids())->toBe(['p1.f1', 'p1.f2', 'p1.f3'])
        ->and(dbFound($scene))->toBe([])
        ->and($scene->lesson_status)->toBe('ready');
});

// Наряд GEN-4, 3.9: «exchange/check/listening на диалоге». Catches a check repaired without the dialogue it belongs to, one
// sent neighbours that only an exchange reads, and the repaired check not the one stored.
it('repairs a check of the dialogue with the dialogue in hand, and stores the repaired check', function () {
    $fake = new FakePlanModel(
        lesson: static function (LessonRequest $request): array {
            $p = planCleanLesson($request);
            // The right option says «earlier this» of A's «Did it start today, or earlier this week?» (`check.verbatim`).
            $p['dialogue'][1]['check']['options'][1] = ['text_target' => 'Earlier this week', 'text_native' => 'На днях'];

            return $p;
        },
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            $card['options'][$card['correct_option_index']] = ['text_target' => 'A few days before', 'text_native' => 'Несколько дней назад'];

            return ['card' => $card];
        },
    );
    [, , $scene] = dbBuild($this, $fake);
    $check = json_decode((string) $scene->lesson_json, true)['dialogue'][1]['check'];

    expect(array_map(static fn (LessonCardRepairRequest $r): string => "{$r->kind}:{$r->address}", $fake->repairRequests))->toBe(['check:x2.check'])
        ->and($fake->repairRequests[0]->dialogue)->not->toBeNull()
        ->and($fake->repairRequests[0]->neighbours)->toBeNull()
        ->and($check['options'][$check['correct_option_index']]['text_target'])->toBe('A few days before')
        ->and(dbFound($scene))->toBe([]);
});

// Наряд GEN-4, 3.7: «сервер перемешивает варианты (seed = id сцены)». Catches a day stored with the model's habit — every right
// answer where the model put it — a shuffle that loses which option is right, and one another seed than the scene's would
// deal again otherwise.
it('stores every check and listening question shuffled by the scene\'s seed, the right option kept', function () {
    [, , $scene] = dbBuild($this, new FakePlanModel);
    $stored = json_decode((string) $scene->lesson_json, true);
    $request = FakePlanModel::lessonRequest();
    $written = (new LessonParser)->dialogue(FakePlanModel::dialoguePayload(new DialogueRequest($request, planFixtureDay($request)[0])));
    $expected = OptionShuffle::of($written, (string) $scene->id);

    // Stored as jsonb, which keeps no order of keys: the same fields, the options in the same order.
    foreach ($expected->exchanges as $i => $exchange) {
        expect($stored['dialogue'][$i]['check'])->toEqual($exchange->exchange->check->toArray());
    }
    foreach ($expected->listening as $i => $question) {
        expect($stored['listening']['questions'][$i])->toEqual($question->toArray());
    }
    expect(array_map(static fn ($e): ?string => $e->exchange->check->correctOption()?->textTarget, $expected->exchanges))
        ->toBe(array_map(static fn ($e): ?string => $e->exchange->check->correctOption()?->textTarget, $written->exchanges))
        // Moved: not every right option stands where the model put it.
        ->and(array_map(static fn ($e): int => $e->exchange->check->correctOptionIndex, $expected->exchanges))
        ->not->toBe(array_map(static fn ($e): int => $e->exchange->check->correctOptionIndex, $written->exchanges));
});

// Canon: «LEARNER_GENDER из профиля, unknown по умолчанию; факты о ученике из цели плана — в TOPIC_DESCRIPTION». Catches a gender
// read once and cached, and the learner's own words left out of the brief.
it('writes a day with the learner\'s gender as the profile says it now and the learner\'s own words beside the brief', function () {
    [$user, $token] = planLearner();
    $fake = new FakePlanModel;
    app()->instance(PlanModelPort::class, $fake);

    planCreate($this, $token, ['days_total' => 2, 'goal_text' => 'Собеседование в пятницу. У меня пять лет опыта в продажах']);
    DB::table('profiles')->where('user_id', $user->id)->update(['gender' => 'female']);
    $plan = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();
    planWalkDay($this, $token, $plan['id'], 1);

    [$first, $second] = $fake->skeletonRequests;
    $prompts = new PlanPromptFiles;

    expect($first->learnerGender)->toBeNull()
        ->and($second->learnerGender)->toBe(VoiceGender::Female)
        ->and($first->topicDescription)->toEndWith("\n\nAbout the learner, in their own words: Собеседование в пятницу. У меня пять лет опыта в продажах")
        ->and($prompts->skeletonUser($first))->toContain("LEARNER_GENDER: unknown\n")
        ->and($prompts->skeletonUser($second))->toContain("LEARNER_GENDER: female\n")
        ->and($fake->dialogueRequests[1]->lesson->learnerGender)->toBe(VoiceGender::Female);
});
