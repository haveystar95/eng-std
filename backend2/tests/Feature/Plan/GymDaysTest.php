<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

/**
 * THE OWNER'S GYM DAYS AS THE PHONE READS THEM NOW (наряд FIX-3 on the data of GYM-DUMP-2): the plan «Тренировка в
 * зале», its day 1 «Ресепшен зала» closed and its day 2 «С тренером» walked, loaded as the live server held them — and
 * read by today's code, the voice switched on over the fake vendor.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
});

/** @return array<string, mixed> */
function gymRoom(object $ctx, string $token, string $id, int $number): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/{$number}")->assertOk()->json('data');
}

/** The key of this environment's pack for a voice of that role and gender. */
function gymVoiceKey(string $speaker, string $gender): string
{
    return (string) app(App\Modules\Plan\Application\Port\LineSpeaker::class)->voiceKeyFor(
        'en', App\Modules\Plan\Domain\ValueObject\Speaker::from($speaker), App\Modules\Shared\Domain\ValueObject\VoiceGender::from($gender),
    );
}

/** The audio id of an absolute `…/plans/audio/{id}` address. */
function gymAudioId(?string $url): ?string
{
    return $url === null ? null : basename($url);
}

/**
 * Canon (наряд FIX-3 §1): «карточка-возврат несёт файл звука сцены-источника, не переозвучивается (закрепить на данных
 * зала: возвраты дня 2 → файлы сцены «Ресепшен зала»)»; «голос ученика — из пола профиля; пока его нет — мужской».
 * CATCHES a returned line looked up in the day's own scene, a return re-voiced, and the learner's voice taken as «the
 * partner's other gender» (scene 2's partner is a man: its learner files were bought female, and nobody reads them now).
 */
it('gives the returned lines of day 2 the files of «Ресепшен зала», in the learner\'s own voice', function () {
    [$user, $token] = planLearner();
    $id = planGymLoad((string) $user->id);
    $reception = '01M32DXHYG50H7SWQEMD33A39F';
    $trainer = '01M32DXHYGYMK0E1DBR01PYDHA';
    $files = DB::table('plan_line_audios')->pluck('scene_id', 'id')->all();
    $voice = DB::table('plan_line_audios')->pluck('voice_key', 'id')->all();

    $cards = array_merge(...array_column(gymRoom($this, $token, $id, 2)['stages'], 'cards'));
    $back = array_values(array_filter($cards, static fn (array $c): bool => $c['source'] === 'returned'));
    $ownSpeak = array_values(array_filter($cards, static fn (array $c): bool => $c['source'] === 'today' && $c['kind'] === 'speak_answer'));

    expect($back)->toHaveCount(7)
        ->and(array_unique(array_column($back, 'kind')))->toBe(['speak_retell'])
        ->and(array_column(array_column(array_column($back, 'payload'), 'own_line'), 'ref'))->toBe(['x1b', 'x2b', 'x3b', 'x4b', 'x5b', 'x6b', 'x8b']);
    foreach ($back as $card) {
        $audio = gymAudioId($card['payload']['own_line']['audio']['url']);
        expect($audio)->not->toBeNull($card['unit']['ref'])
            ->and($files[$audio])->toBe($reception)
            ->and($voice[$audio])->toBe(gymVoiceKey('learner', 'male'));
    }
    // The day's own lines of «С тренером» were bought in the voice their learner no longer has: nothing is re-voiced,
    // the phone says them with its own voice until `plan:revoice-learner` is run.
    expect(array_filter(array_map(static fn (array $c): ?string => $c['payload']['own_line']['audio']['url'], $ownSpeak)))->toBe([])
        ->and(DB::table('plan_line_audios')->where('scene_id', $trainer)->where('line_ref', 'x1b')->value('voice_key'))->toBe(gymVoiceKey('learner', 'female'));
});

/**
 * Canon (наряд FIX-3 §9): «program.{words,phrases,dialogue}.lines[] получают source (own | returned) и scene {id,
 * title_native, day_number}; summary.returns = число возвратов. На зале день 2: dialogue — 8 own + 7 returned из «Ресепшен
 * зала», returns 7». CATCHES a returned line drawn as the day's own, a line without the scene it came from, and the brow
 * counting what goes back tomorrow instead of what came.
 */
it('names on the dialogue tab of day 2 the eight lines of its own and the seven that came back from «Ресепшен зала»', function () {
    [$user, $token] = planLearner();
    $id = planGymLoad((string) $user->id);
    $dialogue = gymRoom($this, $token, $id, 2)['window']['program']['dialogue'];
    $own = array_values(array_filter($dialogue['items'], static fn (array $i): bool => $i['source'] === 'own'));
    $back = array_values(array_filter($dialogue['items'], static fn (array $i): bool => $i['source'] === 'returned'));

    expect($own)->toHaveCount(8)
        ->and($back)->toHaveCount(7)
        ->and($dialogue['summary']['returns'])->toBe(7)
        ->and(array_unique(array_column(array_column($own, 'scene'), 'title_native')))->toBe(['С тренером'])
        ->and(array_unique(array_column(array_column($own, 'scene'), 'day_number')))->toBe([2])
        ->and($back[0]['scene'])->toBe(['id' => '01M32DXHYG50H7SWQEMD33A39F', 'title_native' => 'Ресепшен зала', 'day_number' => 1])
        ->and(array_unique(array_column(array_column($back, 'scene'), 'id')))->toBe(['01M32DXHYG50H7SWQEMD33A39F']);
});

/**
 * Canon (наряд FIX-3 §8): «этапы карточек — again всегда true, и у закрытых дней (GET комнаты закрытого дня отдаёт
 * карточки — проверить, что день 1 после открытия дня 2 читается)». CATCHES a closed day read without its cards — the
 * phone's «Ещё раз» would have nothing to walk — and a closed day's stage offered no «Ещё раз».
 */
it('reads closed day 1 with its cards after day 2 is open, every stage of cards offered again', function () {
    [$user, $token] = planLearner();
    $id = planGymLoad((string) $user->id);
    $day1 = gymRoom($this, $token, $id, 1);
    $cardStages = array_values(array_filter($day1['window']['stages'], static fn (array $s): bool => $s['stage'] !== 'conversation'));

    expect($day1['day']['status'])->toBe('closed')
        ->and(array_sum(array_map(static fn (array $s): int => count($s['cards']), $day1['stages'])))->toBe(85)
        ->and(array_unique(array_column($cardStages, 'again')))->toBe([true])
        ->and(array_column($cardStages, 'summary'))->each->toHaveKeys(['done', 'total', 'first_try', 'returns'])
        ->and($day1['window']['allowed_action'])->toBeNull();
});
