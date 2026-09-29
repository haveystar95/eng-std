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
 * repair, four cards a stage at most (two before наряд GEN-4c), and a repair is kept only when it brings no fatal finding.
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
        ->and($scene->prompt_version_lesson)->toBe('lesson_skeleton.v1.1+lesson_dialogue.v1.1')
        ->and(array_column($skeleton['phrases'], 'must_say'))->toBe([[1], [2], [3], [4], [5], [6, 7]])
        ->and(array_column($skeleton['partner_lines'], 'id'))->toBe(['a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7'])
        ->and($fake->dialogueRequests[0]->dialogueCount)->toBe(8)
        // The seam judge reads every native frame with a window, each filler once — before the dialogue exists.
        ->and($fake->judgeRequests[0]->ids())->toHaveCount(15)
        ->and($lesson)->not->toContain('must_say')->not->toContain('must_understand')->not->toContain('partner_line')->not->toContain('pairs_with')
        ->and(DB::table('plan_check_counters')->whereIn('prompt_version', ['lesson_skeleton.v1.1', 'lesson_dialogue.v1.1'])->count())->toBe(0);
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
        ->and(dbCounted('lesson_skeleton.v1.1', 'frame.must_say'))->toEqualCanonicalizing($again ? ['counted' => 2, 'gated' => 2, 'failed' => 1] : ['counted' => 1, 'gated' => 1]);
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
        ->and(dbCounted('lesson_dialogue.v1.1', 'partner.changed'))->toEqualCanonicalizing($again ? ['counted' => 2, 'gated' => 2, 'failed' => 1] : ['counted' => 1, 'gated' => 1]);
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
        ->and(DB::table('plan_check_counters')->where('prompt_version', 'lesson_skeleton.v1.1')->count())->toBe(0);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/scenes/{$scene->id}/lesson/retry")->assertStatus(202);
    expect($fake->skeletonCalls)->toBe(3)
        ->and(dbScene($id)->lesson_status)->toBe('ready');
});

/**
 * The fake's clean day with a warning on each of five cards of the skeleton — a placeholder word, a reply to a yes-or-no
 * question with no «No», a frame, a partner line, a word defined in the learner's language.
 */
function dbFiveWarnings(LessonRequest $request): array
{
    $p = planCleanLesson($request);
    // v2 «dull» — said only through p3's filler «dull», a value the learner never gave (`vocab.from_placeholder`).
    $p['vocabulary'][1]['term_target'] = 'dull';
    // x8's A line — a7 of the skeleton — answers «Do we need a follow-up appointment?» with no «No» (`partner.yes_no_missing`).
    $p['dialogue'][7]['messages'][1]['text_target'] = 'Only if it still hurts after one week.';
    // p2: eight words before its window (`frame.too_long`), said so in its exchange.
    $p['phrases'][1]['frame_target'] = 'It started, as far as I can tell, ___.';
    $p['dialogue'][1]['messages'][1]['text_target'] = 'It started, as far as I can tell, three days ago.';
    // a1 names the filler of the frame it pairs with (`partner.names_filler`).
    $p['dialogue'][0]['messages'][0]['text_target'] = 'Is it his lower back that hurts?';
    // v8 defined in the learner's language (`vocab.definition_language`).
    $p['vocabulary'][7]['definition_target'] = 'записка от врача для школы';

    return $p;
}

// Наряд GEN-4c §4: «бюджет починок: скелет 2 → 4 карточек; порядок: бюджетные фатальные (foreign_script) → vocab.from_placeholder
// → yes_no → names_filler_meaning → остальные в прежнем порядке» — the order the cards are TAKEN in; the ones taken are repaired
// frames first, then lines, then words (GEN-4: what the others are built on goes first — the e2e of GEN-4c lost a line's repair
// to a word repaired before it). GEN-4, 3.9: «после починки partner_line код заменяет реплику A»; the seam judge reads again
// what a repair changed. Catches a fifth card repaired, the placeholder word or the yes-or-no reply left out for the frames, a
// word repaired before the lines it may take its new word from, a repair kept that the dialogue then says the old way, and a
// repaired frame or reply never read by the judge again.
it('takes four cards of the skeleton\'s warnings, the placeholder word and the yes-or-no reply before the rest, repairs them frames first, and writes the dialogue over the repaired skeleton', function () {
    $fake = new FakePlanModel(
        lesson: dbFiveWarnings(...),
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            if ($request->address === 'v2') {
                $card = [...$card, 'term_target' => 'ache', 'translation_native' => 'ноющая боль', 'pronunciation_native' => 'эйк', 'definition_target' => 'a pain that goes on and is not strong', 'used_in' => ['a3']];
            }
            if ($request->address === 'a7') {
                $card['text_target'] = 'No, only if it still hurts after one week.';
            }
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

    expect(array_map(static fn (LessonCardRepairRequest $r): string => "{$r->kind}:{$r->address}", $fake->repairRequests))->toBe(['frame:p2', 'partner_line:a1', 'partner_line:a7', 'term:v2'])
        ->and(array_column($fake->repairRequests[3]->findings, 'code'))->toBe(['vocab.from_placeholder'])
        ->and(array_column($fake->repairRequests[2]->findings, 'code'))->toBe(['partner.yes_no_missing'])
        ->and($fake->repairRequests[2]->findings[0]['detail'])->toStartWith('a7: a yes-or-no question: start with Yes. or No., then one general fact')
        // The word is repaired over the lines as the repairs left them.
        ->and($fake->repairRequests[3]->skeleton['partner_lines'][6]['text_target'])->toBe('No, only if it still hurts after one week.')
        ->and($fake->repairRequests[3]->dialogue)->toBeNull()
        ->and($scene->lesson_status)->toBe('ready')
        // The word defined in Russian was the fifth card: its warning stays with the day.
        ->and(dbFound($scene))->toBe(['vocab.definition_language@v8'])
        ->and($fake->dialogueRequests[0]->skeleton->frame('p2')?->phrase->frameTarget)->toBe('It started ___.')
        ->and($lesson['phrases'][1]['frame_target'])->toBe('It started ___.')
        ->and($lesson['vocabulary'][1]['term_target'])->toBe('ache')
        ->and($lesson['dialogue'][1]['messages'][1]['text_target'])->toBe('It started three days ago.')
        ->and($lesson['dialogue'][0]['messages'][0]['text_target'])->toBe('Where does it hurt: in his upper back or lower down?')
        ->and($lesson['dialogue'][7]['messages'][1]['text_target'])->toBe('No, only if it still hurts after one week.')
        // Read again, in one call: the frame's native seams and the reply to the learner's question; a1 asks, it is no reply.
        ->and($fake->judgeCalls)->toBe(2)
        ->and($fake->judgeRequests[1]->ids())->toBe(['p2.f1', 'p2.f2', 'p2.f3'])
        ->and($fake->judgeRequests[1]->replyIds())->toBe(['a7']);
});

// «Repair kept only if no new fatal»: a repair that breaks what the check holds is thrown away, the card as the stage wrote it
// kept with its warning. Catches a repair written into the day unchecked — here a partner line shortened past the one word of
// the day it alone says («heating pad», `vocab.not_found`, fatal) — and a day failed over a repair it could have done without.
it('keeps the card as written when its repair brings a fatal finding', function () {
    $long = 'It looks like a muscle strain, so he should take it easy, keep warm and use a heating pad in the evening.';
    $fake = new FakePlanModel(
        lesson: static function (LessonRequest $request) use ($long): array {
            $p = planCleanLesson($request);
            $p['dialogue'][4]['messages'][0]['text_target'] = $long;

            return $p;
        },
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => [...$request->card, 'text_target' => 'It looks like a muscle strain, so he should rest.']],
    );
    [, , $scene] = dbBuild($this, $fake);
    $lesson = json_decode((string) $scene->lesson_json, true);

    expect(array_map(static fn (LessonCardRepairRequest $r): string => $r->address, $fake->repairRequests))->toBe(['a5'])
        // Наряд GEN-4c: the repair is told the word only this line says — a note, no finding: counted nowhere, stored nowhere.
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['partner.too_long', 'vocab.carried'])
        ->and($fake->repairRequests[0]->findings[1]['detail'])->toContain('«heating pad» (v5)')
        ->and(DB::table('plan_check_counters')->where('check_name', 'vocab.carried')->count())->toBe(0)
        ->and($scene->lesson_status)->toBe('ready')
        ->and(dbFound($scene))->toBe(['partner.too_long@a5'])
        ->and($lesson['dialogue'][4]['messages'][0]['text_target'])->toBe($long);
});

// Наряд GEN-4b §3: «pronunciation.foreign_script — из фатальных в предупреждения: находка идёт в починку карточки (frame /
// partner_line / term с этим чтением), фатально только если таких карточек больше бюджета починок ступени» — four since GEN-4c.
// Catches a letter of another writing still asking the skeleton again, its card left behind the other warnings' — and cards
// beyond the stage's four repairs let through into a day.
it('sends a reading with a letter of another writing to a repair first, and asks the skeleton again only beyond four cards', function (int $cards, bool $again) {
    $readings = ['p2' => 'ит стартэд ___ ק', 'p3' => 'зэ пэйн из ___ вэн хи бэндз ק', 'p4' => 'хи дазнт хэв э фивер ק', 'p5' => 'хи уил рэст ___ ק', 'p6' => 'ду уи нид ___ ק'];
    $fake = new FakePlanModel(
        skeleton: static function (LessonRequest $request) use ($readings, $cards): array {
            $s = FakePlanModel::stagesOf(planCleanLesson($request), $request)['skeleton'];
            if ($request->previousViolations !== []) {
                return $s;
            }
            foreach (array_slice($readings, 0, $cards, true) as $id => $reading) {
                foreach ($s['phrases'] as $i => $phrase) {
                    if ($phrase['id'] === $id) {
                        $s['phrases'][$i]['pronunciation_native'] = $reading;
                    }
                }
            }
            // The first frame's own warning — by kind and address its card would be repaired first.
            $s['phrases'][0]['frame_target'] = 'It hurts a lot right here in his ___.';

            return $s;
        },
        repair: static function (LessonCardRepairRequest $request): array {
            $card = $request->card;
            if (isset($card['pronunciation_native'])) {
                $card['pronunciation_native'] = trim(str_replace('ק', '', (string) $card['pronunciation_native']));
            }

            return ['card' => $card];
        },
    );
    [, , $scene] = dbBuild($this, $fake);

    expect($fake->skeletonCalls)->toBe($again ? 2 : 1)
        ->and($scene->lesson_status)->toBe('ready');
    if ($again) {
        expect($fake->skeletonRequests[1]->previousViolations)->toHaveCount(5)
            ->and($fake->skeletonRequests[1]->previousViolations[0])->toStartWith('pronunciation.foreign_script · p2:');
    } else {
        // The four readings repaired first, the first frame's own warning (the fifth card) left with the day.
        expect(array_map(static fn (LessonCardRepairRequest $r): string => $r->address, $fake->repairRequests))->toBe(['p2', 'p3', 'p4', 'p5'])
            ->and(dbFound($scene))->toBe(['frame.too_long@p1']);
    }
})->with([
    'four cards — the repairs take them' => [4, false],
    'five cards — beyond the stage\'s repairs' => [5, true],
]);

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

// Наряд GEN-4c (the e2e ru→en): the cards are taken once and repaired frames first — a word whose finding the repair of a frame
// took away is sent to no repair: the e2e paid for a word sent with no finding. Catches that call bought again.
it('sends no card to a repair whose findings an earlier repair of the stage took away', function () {
    $fake = new FakePlanModel(
        lesson: static function (LessonRequest $request): array {
            $p = planCleanLesson($request);
            // v5 «heating pad» named in p5, which does not say it (`vocab.used_in_wrong`); p5 eight words long (`frame.too_long`).
            $p['vocabulary'][4]['used_in'] = ['p5'];
            $p['phrases'][4]['frame_target'] = 'He will rest, as the doctor told him to, ___.';
            $p['dialogue'][4]['messages'][1]['text_target'] = 'Okay, he will rest, as the doctor told him to, at home.';

            return $p;
        },
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => $request->address === 'p5'
            ? [...$request->card, 'frame_target' => 'He will rest with a heating pad ___.']
            : $request->card],
    );
    [, , $scene] = dbBuild($this, $fake);

    expect(array_map(static fn (LessonCardRepairRequest $r): string => $r->address, $fake->repairRequests))->toBe(['p5'])
        ->and(dbFound($scene))->toBe([])
        ->and($scene->lesson_status)->toBe('ready');
});

// Наряд GEN-4c §3: «судья: называет ли ответ хотя бы одно наполнение этого каркаса … partner.names_filler_meaning — предупреждение
// с бюджетом, карточка partner_line»; «все новые коды — в plan_check_counters». Catches a named reply sent to no repair, a
// repaired reply the judge never reads again (so its finding would stay or vanish unread), a second call bought for the second
// question, and the code left out of the counters.
it('sends a reply the seam judge finds naming a filler to a repair, and has the judge read that reply again', function () {
    $fake = new FakePlanModel(
        repair: static fn (LessonCardRepairRequest $request): array => ['card' => [...$request->card, 'text_target' => 'No, only if it still hurts after one week.']],
        judge: static fn (NativeSeamJudgeRequest $request, int $call): array => [
            'verdicts' => array_map(static fn (string $id): array => ['id' => $id, 'reads' => true], $request->ids()),
            // a7 — the reply to «Do we need ___?» of x8 — named once; the line the repair writes names nothing.
            'replies_naming_values' => $call === 1 ? ['a7'] : [],
        ],
    );
    [, , $scene] = dbBuild($this, $fake);

    expect(array_map(static fn (LessonCardRepairRequest $r): string => "{$r->kind}:{$r->address}", $fake->repairRequests))->toBe(['partner_line:a7'])
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['partner.names_filler_meaning'])
        ->and($fake->judgeCalls)->toBe(2)
        ->and($fake->judgeRequests[0]->replyIds())->toBe(['a6', 'a7'])
        ->and($fake->judgeRequests[1]->ids())->toBe([])
        ->and($fake->judgeRequests[1]->replyIds())->toBe(['a7'])
        ->and(dbFound($scene))->toBe([])
        ->and($scene->lesson_status)->toBe('ready')
        ->and(dbCounted('lesson_skeleton.v1.1', 'partner.names_filler_meaning'))->toBe(['counted' => 1]);
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
