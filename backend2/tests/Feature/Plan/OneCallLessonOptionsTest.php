<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\OptionShuffle;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

// Canon (наряд GEN-4, 3.7; DECISIONS п. 452): the options stand where the server put them when the day was built, and the
// served lesson moves nothing. A lesson written in one call before GEN-4 was stored in the model's order — the right answer
// first nine times out of ten — and shuffled at every reading; the migration shuffles each such lesson once, with the seeds
// of that reading. Catches the days of plans built before GEN-4 dealt with the right option first — and a lesson of the two
// stages, shuffled at its build, moved a second time.

/** The migration of the stored one-call lessons, run over what the test stored. */
function oclShuffleStored(): void
{
    (require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_29_100300_shuffle_options_of_one_call_lessons.php'))->up();
}

/**
 * Every check's options and its right index, then every listening question's — in the order they are stored, each option
 * with its keys sorted (jsonb keeps the keys of an object in an order of its own).
 *
 * @param  array<string, mixed>  $lesson
 * @return list<array{0: string, 1: int}>
 */
function oclOptions(array $lesson): array
{
    $canon = static function (array $options): string {
        return (string) json_encode(array_map(static function (mixed $o): mixed {
            if (is_array($o)) {
                ksort($o);
            }

            return $o;
        }, $options), JSON_UNESCAPED_UNICODE);
    };
    $out = [];
    foreach ($lesson['dialogue'] as $exchange) {
        $out[] = [$canon($exchange['check']['options']), (int) $exchange['check']['correct_option_index']];
    }
    foreach ($lesson['listening']['questions'] as $question) {
        $out[] = [$canon($question['options_native']), (int) $question['correct_option_index']];
    }

    return $out;
}

/** @return array<string, mixed> */
function oclStored(string $sceneId): array
{
    return json_decode((string) DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json'), true);
}

it('puts the options of a lesson written in one call where its reading put them, once, and leaves a lesson of the two stages', function () {
    app()->instance(App\Modules\Plan\Application\Port\PlanModelPort::class, new FakePlanModel);
    [, $token] = planLearner();
    $planId = planCreate($this, $token, ['days_total' => 1])['id'];
    $built = DB::table('plan_scenes')->where('plan_id', $planId)->first() ?? throw new LogicException('no scene');

    // A second scene of the plan holds a lesson of before GEN-4: the model's answer as it wrote it, no skeleton beside it.
    $oneCall = FakePlanModel::lessonPayload(FakePlanModel::lessonRequest());
    $oldId = (string) Str::ulid();
    DB::table('plan_scenes')->insert([...array_diff_key((array) $built, array_flip(['id', 'order'])),
        'id' => $oldId, 'order' => 99, 'lesson_json' => json_encode($oneCall), 'skeleton_json' => null]);
    $before = oclStored($oldId);
    $twoStages = oclStored($built->id);

    oclShuffleStored();

    $after = oclStored($oldId);
    $served = OptionShuffle::lesson((new LessonParser)->parse($before), $oldId)->toArray();
    expect(oclOptions($after))->toBe(oclOptions($served))
        ->and(oclOptions($after))->not->toBe(oclOptions($before))
        ->and(oclStored($built->id))->toBe($twoStages);
    // The right answer is the same option, only elsewhere.
    foreach ($before['dialogue'] as $i => $exchange) {
        expect($after['dialogue'][$i]['check']['options'][$after['dialogue'][$i]['check']['correct_option_index']])
            ->toEqual($exchange['check']['options'][$exchange['check']['correct_option_index']]);
    }
});
