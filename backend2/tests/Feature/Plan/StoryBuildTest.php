<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * DAY N KNOWS THE DAYS BEFORE IT (наряд GEN-3) — through the build as production runs it: the next day's lesson is asked with
 * the story so far and spoken in the plan's roles; a word the learner already learned holds the day for a repair of that
 * word, and a repaired word the server's own check refuses is no repair.
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

/** Day 2's lesson with its second word said as day 1 said it — «sharp», a word the learner has learned. */
function sbTeachesSharpAgain(LessonRequest $request): array
{
    $p = planCleanLesson($request);
    if (! $request->earlierDays->isEmpty()) {
        $p['vocabulary'][1]['term_target'] = 'sharp';
        // An acronym the day says, so that a repair may take it for a word.
        $p['dialogue'][2]['messages'][0]['text_target'] .= ' An MRI is not needed yet.';
    }

    return $p;
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
    [$dayOne, $dayTwo] = $fake->lessonRequests;
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

// Наряд GEN-3, §4: «vocab.known_repeat — термин любого прошлого готового дня плана → P2R, вид карточки term»; §5: «после ответа
// сервер перепроверяет словарь». Catches a day 2 dealt with a word day 1 taught, a repair asked for another card or without
// what day 1 taught, and a repaired word stored under the model's id.
it('holds day 2 for a word day 1 taught, repairs that word and stores the day', function () {
    $fake = new FakePlanModel(
        lesson: sbTeachesSharpAgain(...),
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => [
            'id' => 'v5', 'term_target' => 'bend', 'translation_native' => 'наклоняться', 'pronunciation_native' => 'бэнд',
            'definition_target' => 'to move the body forward and down', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['p3'],
        ]],
    );
    [, , $scenes] = sbTwoDays($this, $fake);
    $words = array_column(json_decode((string) $scenes[1]->lesson_json, true)['vocabulary'], 'term_target', 'id');

    expect($fake->repairCalls)->toBe(1)
        ->and($fake->repairRequests[0]->kind)->toBe('term')
        ->and($fake->repairRequests[0]->address)->toBe('v2')
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['vocab.known_repeat'])
        ->and($fake->repairRequests[0]->earlierDays->days[0]->words)->toContain('sharp')
        ->and($fake->repairRequests[0]->neighbours)->toBeNull()
        ->and($scenes[1]->lesson_status)->toBe('ready')
        ->and($words['v2'])->toBe('bend')
        ->and($words['v5'])->toBe('heating pad-2');
});

// Наряд GEN-3, §5: «перепроверка после term: used_in точен, термина нет среди прошлых дней и в словаре дня дважды»; «иначе — как
// любая неудавшаяся починка». Catches a repaired word stored that is a word of day 1, a word the day already has, or a word the
// lesson never says — each one a card that teaches nothing or teaches twice. The day fails after the one rebuild the server
// makes on its own (наряд LANG-1b §1): the fake writes the same lesson again and its repair is refused again — a card a build.
it('refuses a repaired word that is a learned word, a word the day already has or a word the lesson never says', function (array $word) {
    $fake = new FakePlanModel(
        lesson: sbTeachesSharpAgain(...),
        repair: static fn (): array => ['card' => [
            'id' => 'v2', 'translation_native' => 'x', 'pronunciation_native' => 'x', 'definition_target' => 'x', 'kind' => 'word', 'image_prompt' => null,
        ] + $word],
    );
    [$token, $id, $scenes] = sbTwoDays($this, $fake);

    expect($fake->repairCalls)->toBe(2)
        ->and($scenes[1]->lesson_status)->toBe('failed')
        ->and($scenes[1]->fail_reason)->toBe('fatal: vocab.known_repeat')
        ->and(planRead($this, $token, $id)['scenes'][1]['lesson_status'])->toBe('failed');
})->with([
    'a word of day 1' => [['term_target' => 'lower back', 'used_in' => ['p1', 'A1']]],
    'a word of the day under another id' => [['term_target' => 'fever-2', 'used_in' => ['p4', 'A7']]],
    'a word the lesson never says' => [['term_target' => 'crutches', 'used_in' => ['p3']]],
]);

// Доработка GEN-3: «vocab.abbreviation — из фатальных в предупреждения: аббревиатура допустима словом дня, если в NATIVE_LANGUAGE
// есть обычное слово (ATM → банкомат, PIN → ПИН-код); судит модель по правилу v4.7, код только считает». Catches a repaired word
// refused for being an acronym — a paid repair thrown away and the day failed over a word the model was allowed to choose — and
// an acronym left uncounted.
it('takes a repaired word that is an abbreviation, and only counts it', function () {
    $fake = new FakePlanModel(
        lesson: sbTeachesSharpAgain(...),
        repair: static fn (): array => ['card' => [
            'id' => 'v2', 'term_target' => 'MRI', 'translation_native' => 'МРТ', 'pronunciation_native' => 'эм-ар-ай',
            'definition_target' => 'a scan that shows the inside of the body', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['A3'],
        ]],
    );
    [, , $scenes] = sbTwoDays($this, $fake);
    $words = array_column(json_decode((string) $scenes[1]->lesson_json, true)['vocabulary'] ?? [], 'term_target', 'id');
    $found = array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", json_decode((string) $scenes[1]->checks_json, true) ?? []);

    expect($fake->repairCalls)->toBe(1)
        ->and($scenes[1]->lesson_status)->toBe('ready')
        ->and($words['v2'] ?? null)->toBe('MRI')
        ->and($found)->toContain('vocab.abbreviation@v2');
});

// Доработка GEN-3: «в FINDINGS починки каркаса по frame.known_repeat цитировать только совпадение frame_target — в v1.3 тождество
// при починке только по TARGET_LANGUAGE, родной каркас — перевод»; решение архитектора: «лишними были только находки о тождестве
// родного шаблона (known_native_repeat, twin по родному) — они и толкали модель выдумывать „Что с ним? — ___.“; швы и фатальные
// находки наполнений резать нельзя». Catches a frame repair told that its native pattern is another frame's — the model then
// bends a plain translation into a device — and a repair not told what else is wrong with the frame.
it('tells the repair of a learned frame its target match and the frame\'s other findings, not a native pattern it shares', function () {
    $fake = new FakePlanModel(
        lesson: static function (LessonRequest $request): array {
            $p = planCleanLesson($request);
            if (! $request->earlierDays->isEmpty()) {
                // Day 1's frame said again, with no closing mark, and with the native pattern of day 2's own p2.
                $p['phrases'][4]['frame_target'] = 'He will rest ___';
                $p['phrases'][4]['frame_native'] = $p['phrases'][1]['frame_native'];
                $p['dialogue'][4]['messages'][1]['text_target'] = 'Okay, he will rest at home.';
            }

            return $p;
        },
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            $card['frame_target'] = 'He is going to rest ___.';

            return ['card' => $card];
        },
    );
    [, , $scenes] = sbTwoDays($this, $fake);
    $told = $fake->repairRequests[0]->findings ?? [];
    $stored = array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", json_decode((string) $scenes[1]->checks_json, true));

    // The native twin is there — it outlives the repair as the warning it is — and still the repair was not told it.
    expect($stored)->toContain('frame.twin@p5')
        ->and($fake->repairCalls)->toBe(1)
        ->and($fake->repairRequests[0]->address)->toBe('p5')
        ->and(array_column($told, 'code'))->toEqualCanonicalizing(['frame.no_end_punct', 'frame.known_repeat'])
        ->and(implode(' ', array_column($told, 'detail')))->toContain('«He will rest ___»')->not->toContain('Началось')
        ->and($scenes[1]->lesson_status)->toBe('ready');
});
