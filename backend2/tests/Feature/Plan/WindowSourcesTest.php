<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * THE WINDOW OF A REVIEW AND A REHEARSAL — WHAT THEY ARE MADE OF AND HOW LONG EACH ROW TAKES (наряд BACK-TAILS-2 §4),
 * AND ONE NAME FOR A SCENE (§6). `window.sources[]` names the scenes a day is made of; every row of `window.stages[]`
 * carries its planned `minutes` in every state, and the talk's row the targets of its talk; a scene is called what its
 * plan calls it — on the sheet of «Вспомнить» too — and the sheets dealt before are renamed by `plan:reconcile-scenes`.
 */

/** @return array{token: string, id: string} a plan of five days (scene, scene, review, scene, rehearsal), started */
function wsPlan(object $ctx): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token)['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    return ['token' => $token, 'id' => $id];
}

/** @return array<string, mixed> the window of a day */
function wsWindow(object $ctx, string $token, string $id, int $number): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/{$number}")->assertOk()->json('data.window');
}

/** @return array<string, mixed> the talk's row of a day's window */
function wsTalkRow(object $ctx, string $token, string $id, int $number): array
{
    $rows = array_values(array_filter(wsWindow($ctx, $token, $id, $number)['stages'], static fn (array $s): bool => $s['stage'] === 'conversation'));

    return $rows[0];
}

/** @return array<string, mixed> the day's talk, started — or carried on when one is open */
function wsTalk(object $ctx, string $token, string $id, int $number, array $body = []): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/{$number}/conversation", $body)->assertOk()->json('data');
}

// Canon (§4): «window.sources[]: репетиция — все содержательные сцены плана в порядке дней; повторение — сцены, чьи возвраты
// он берёт (две предыдущие); содержательный день — своя сцена одной записью; {scene_id, title_native, day_number}».
// CATCHES a list the client has to piece together from the cards (CLIENT-CONV-1b §5 п. 1), the review naming its days in
// the wrong order, the rehearsal skipping a scene day, and a name that is not the plan's.
it('names the scenes a day is made of: its own, the two a review repeats, every scene for the rehearsal', function () {
    ['token' => $token, 'id' => $id] = wsPlan($this);
    $plan = planRead($this, $token, $id);
    $scene = static fn (int $day): array => [
        'scene_id' => $plan['days'][$day - 1]['scene_id'],
        'title_native' => $plan['days'][$day - 1]['title_native'],
        'day_number' => $day,
    ];

    expect(array_column($plan['days'], 'type'))->toBe(['scene', 'scene', 'review', 'scene', 'rehearsal'])
        ->and(wsWindow($this, $token, $id, 1)['sources'])->toBe([$scene(1)])
        ->and(wsWindow($this, $token, $id, 3)['sources'])->toBe([$scene(1), $scene(2)])
        ->and(wsWindow($this, $token, $id, 5)['sources'])->toBe([$scene(1), $scene(2), $scene(4)])
        ->and(array_keys(wsWindow($this, $token, $id, 1)['sources'][0]))->toBe(['scene_id', 'title_native', 'day_number']);

    // A scene with no day of its own — the e2e stand spliced one into its plan by hand: the rehearsal is still made of it
    // (its «Вспомнить» and its talk walk every scene of the plan), so it is named — in its place in the plan (last here),
    // with no day to name. CATCHES a rehearsal that lists fewer scenes than it walks.
    $columns = array_values(array_filter(Schema::getColumnListing('plan_scenes'), static fn (string $c): bool => ! in_array($c, ['id', 'order'], true)));
    $quoted = implode(', ', array_map(static fn (string $c): string => '"'.$c.'"', $columns));
    $spliced = '01J8SESS1XTVRESN0DAY000001';
    DB::insert("INSERT INTO plan_scenes (id, \"order\", {$quoted}) SELECT ?, 99, {$quoted} FROM plan_scenes WHERE id = ?", [$spliced, $scene(1)['scene_id']]);
    expect(wsWindow($this, $token, $id, 5)['sources'])->toBe([
        $scene(1), $scene(2), $scene(4), ['scene_id' => $spliced, 'title_native' => $scene(1)['title_native'], 'day_number' => null],
    ])
        // A scene day and a review name only their own.
        ->and(wsWindow($this, $token, $id, 3)['sources'])->toBe([$scene(1), $scene(2)]);
});

// Canon (§4): «stages[].minutes у всех типов дней: этапы карточек — оценка DayPace, вверх до минуты; conversation —
// plan.conversation.minutes (3/6/6); recall — своя оценка (сцены × реплики по прейскуранту)». CATCHES a row of a day not
// opened yet without its minutes (37-1/37-2 printed «впереди» for want of them), the minutes left passed off as the
// stage's, the talk priced by anything but its own budget, and a sheet of three scenes priced as one.
it('gives every row of the window its planned minutes, in every state, on every type of day', function () {
    ['token' => $token, 'id' => $id] = wsPlan($this);
    $pace = config('plan.pace');

    $day1 = wsWindow($this, $token, $id, 1);
    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    $byStage = [];
    foreach ($cards as $card) {
        $each = (int) $pace[$card['kind']];
        $byStage[$card['stage']] = ($byStage[$card['stage']] ?? 0) + match ($card['kind']) {
            'phrase_other_slot' => $each * (count($card['payload']['rounds']) + 1),
            default => $each,
        };
    }
    $planned = array_map(static fn (int $s): int => (int) ceil($s / 60), $byStage);

    expect(array_column($day1['stages'], 'minutes', 'stage'))->toBe([...$planned, 'conversation' => 3])
        // A day not opened yet has them on every row — the rows are all «впереди».
        ->and(array_unique(array_column($day1['stages'], 'state')))->toBe(['locked']);

    // Walked halfway, a done row and a locked one still say what their whole stage takes.
    foreach (array_filter($cards, static fn (array $c): bool => $c['stage'] === 'words') as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    $walking = wsWindow($this, $token, $id, 1);
    expect(array_column($walking['stages'], 'state', 'stage')['words'])->toBe('done')
        ->and(array_column($walking['stages'], 'minutes', 'stage'))->toBe(array_column($day1['stages'], 'minutes', 'stage'));

    // The review's and the rehearsal's talk: six minutes each (`plan.conversation.minutes`).
    $review = wsWindow($this, $token, $id, 3);
    $rehearsal = wsWindow($this, $token, $id, 5);
    expect(array_column($review['stages'], 'minutes', 'stage')['conversation'])->toBe(6)
        ->and(array_column($rehearsal['stages'], 'minutes', 'stage')['conversation'])->toBe(6);

    // «Вспомнить»: a minute of reading a scene — three scenes, three minutes — and the lines said aloud by their price.
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 3);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 4);
    planShiftDay($id);
    $recall = planOpenDay($this, $token, $id, 5)['cards'];
    $sheet = array_values(array_filter($recall, static fn (array $c): bool => $c['kind'] === 'recall_scenes'))[0];
    $aloud = count(array_filter($recall, static fn (array $c): bool => $c['kind'] === 'speak_retell'));
    expect($sheet['payload']['scenes'])->toHaveCount(3)
        ->and(array_column(wsWindow($this, $token, $id, 5)['stages'], 'minutes', 'stage')['recall'])
        ->toBe((int) ceil((3 * (int) $pace['recall_scenes'] + $aloud * (int) $pace['speak_retell']) / 60));
});

// Canon (§4, дополнение по вопросу клиента 1c): «ряд этапа conversation в window.stages получает поле targets — та же форма,
// что targets[] в документе разговора; список — ровно тот, что получит POST …/conversation: один детерминированный селектор
// от дня; said — состояние последнего разговора этого дня, false, если разговора ещё не было; у репетиции — цели по всем
// сценам, у повторения — по своим». CATCHES an entry (37-5) drawn from a list of its own, a row ticked by the first talk
// and not the latest, a phrase said before any talk, a card row with targets, and a review or a rehearsal that asks for
// phrases of scenes not theirs.
it('lists on the talk\'s row the targets its talk starts with, ticked by the day\'s latest talk', function () {
    ['token' => $token, 'id' => $id] = wsPlan($this);
    $plan = planRead($this, $token, $id);
    $sceneOf = static fn (int $day): string => $plan['days'][$day - 1]['scene_id'];
    $saidOf = static fn (array $targets): array => array_column(array_filter($targets, static fn (array $t): bool => $t['said']), 'ref');

    // No talk yet — the day not even opened: the list, nothing said, and no card row with one.
    $before = wsTalkRow($this, $token, $id, 1);
    $cardRows = array_values(array_filter(wsWindow($this, $token, $id, 1)['stages'], static fn (array $s): bool => $s['stage'] !== 'conversation'));
    expect($before['targets'])->not->toBeEmpty()
        ->and($saidOf($before['targets']))->toBe([])
        ->and(array_map(static fn (array $s): mixed => $s['targets'], $cardRows))->each->toBeNull();

    planOpenDay($this, $token, $id, 1);
    $talk = wsTalk($this, $token, $id, 1);
    expect($talk['targets'])->toBe($before['targets']);

    // A move that says p2: the row ticks it as the talk does.
    $moved = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'It started three days ago.'])
        ->assertOk()->json('data');
    expect($saidOf($moved['targets']))->toBe(['p2'])
        ->and(wsTalkRow($this, $token, $id, 1)['targets'])->toBe($moved['targets']);

    // «Ещё раз»: the day's latest talk has said nothing yet — the row is where IT stands, not the first talk.
    $again = wsTalk($this, $token, $id, 1, ['again' => true]);
    expect($saidOf($again['targets']))->toBe([])
        ->and(wsTalkRow($this, $token, $id, 1)['targets'])->toBe($again['targets']);

    // A review over the two scenes it repeats, the rehearsal over every scene of the plan — the POST's list, both.
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);
    planOpenDay($this, $token, $id, 3);
    $review = wsTalkRow($this, $token, $id, 3)['targets'];
    expect($review)->toBe(wsTalk($this, $token, $id, 3)['targets'])
        ->and(array_values(array_unique(array_column($review, 'scene_id'))))->toBe([$sceneOf(1), $sceneOf(2)]);

    planWalkDay($this, $token, $id, 3);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 4);
    planShiftDay($id);
    planOpenDay($this, $token, $id, 5);
    $rehearsal = wsTalkRow($this, $token, $id, 5)['targets'];
    expect($rehearsal)->toBe(wsTalk($this, $token, $id, 5)['targets'])
        ->and(array_values(array_unique(array_column($rehearsal, 'scene_id'))))->toBe([$sceneOf(1), $sceneOf(2), $sceneOf(4)]);
});

// Canon (§6): «источник имени один — сцена плана; при сборке дня имя пишется из плана, все читатели берут его из одного
// места». The fake lesson names itself «At the doctor's with a child» in English; its plan calls the scenes «Booking»,
// «Consultation», «Pharmacy». CATCHES the sheet of «Вспомнить» reading the lesson's own title, and a name that differs
// between the route, the window, the talk and the sheet.
it('calls a scene what its plan calls it — on the route, in the window, in the talk and on the sheet', function () {
    ['token' => $token, 'id' => $id] = wsPlan($this);
    foreach ([1, 2] as $n) {
        planWalkDay($this, $token, $id, $n);
        planShiftDay($id);
    }
    $plan = planRead($this, $token, $id);
    $names = array_column($plan['scenes'], 'title_target', 'id');
    $native = array_column($plan['scenes'], 'title_native', 'id');

    planOpenDay($this, $token, $id, 3);
    $talk = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/3/conversation")->assertOk()->json('data');
    foreach ($talk['scenes'] as $scene) {
        expect($scene['title_native'])->toBe($native[$scene['scene_id']])->and($scene['title_target'])->toBe($names[$scene['scene_id']]);
    }
    foreach (wsWindow($this, $token, $id, 3)['sources'] as $source) {
        expect($source['title_native'])->toBe($native[$source['scene_id']]);
    }

    planWalkDay($this, $token, $id, 3);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 4);
    planShiftDay($id);
    $sheet = array_values(array_filter(planOpenDay($this, $token, $id, 5)['cards'], static fn (array $c): bool => $c['kind'] === 'recall_scenes'))[0];
    foreach ($sheet['payload']['scenes'] as $scene) {
        expect($scene['title_native'])->toBe($native[$scene['scene_id']])
            ->and($scene['title_target'])->toBe($names[$scene['scene_id']])
            ->and($scene['title_target'])->not->toBe("At the doctor's with a child");
    }
});

// Canon (§6): «розданные дни с расхождением — команда plan:reconcile-scenes (dry-run по умолчанию, --apply пишет; вывод:
// план, день, было → стало)». CATCHES a dry run that writes, an apply that writes anything but the names, a second run
// that finds something again, and output that does not say what changed where.
it('renames the scenes of the sheets dealt before, dry by default, and says what it changed where', function () {
    ['token' => $token, 'id' => $id] = wsPlan($this);
    foreach ([1, 2, 3, 4] as $n) {
        planWalkDay($this, $token, $id, $n);
        planShiftDay($id);
    }
    $sheet = array_values(array_filter(planOpenDay($this, $token, $id, 5)['cards'], static fn (array $c): bool => $c['kind'] === 'recall_scenes'))[0];
    $row = DB::table('day_cards')->where('id', $sheet['id'])->first(['payload', 'result']);
    $payload = json_decode((string) $row->payload, true);
    $plan = $payload['scenes'][1];
    // The sheet as the old dealing wrote it: the lesson's own title on its second scene.
    $payload['scenes'][1]['title_native'] = 'У врача с сыном';
    $payload['scenes'][1]['title_target'] = "At the doctor's with a child";
    DB::table('day_cards')->where('id', $sheet['id'])->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)]);

    expect(Artisan::call('plan:reconcile-scenes'))->toBe(0)
        ->and(Artisan::output())->toContain("[dry-run] план {$id} · день 5 · сцена {$plan['scene_id']}: «У врача с сыном» → «{$plan['title_native']}»")
        ->toContain('было 1 / стало 1')
        ->and(json_decode((string) DB::table('day_cards')->where('id', $sheet['id'])->value('payload'), true)['scenes'][1]['title_native'])->toBe('У врача с сыном');

    Artisan::call('plan:reconcile-scenes', ['--apply' => true]);
    $fixed = json_decode((string) DB::table('day_cards')->where('id', $sheet['id'])->value('payload'), true);
    expect(Artisan::output())->toContain('было 1 / стало 0')->toContain('исправлено карточек: 1')
        ->and($fixed['scenes'][1]['title_native'])->toBe($plan['title_native'])
        ->and($fixed['scenes'][1]['title_target'])->toBe($plan['title_target'])
        // Nothing else of the card moved.
        ->and($fixed['scenes'][1]['lines'])->toBe($plan['lines'])
        ->and($fixed['scenes'][0])->toBe($payload['scenes'][0]);

    Artisan::call('plan:reconcile-scenes', ['--apply' => true]);
    expect(Artisan::output())->toContain('было 0 / стало 0');
});
