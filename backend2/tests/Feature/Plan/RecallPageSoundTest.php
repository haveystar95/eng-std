<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

/**
 * «ВСПОМНИТЬ» ЗВУЧИТ ФАЙЛАМИ СВОЕЙ СТРАНИЦЫ (наряд FIX-4 §1) — on the owner's gym plan as the server held it: the rehearsal
 * of day 3 walks two scenes, «Ресепшен зала» and «С тренером», and their lines share their refs (`x1b`…`x8b`). The card
 * of the sheet names the FIRST scene, each page names its own; the second page played the first scene's files, line by
 * line (the check «звук ≠ текст» of ADM-1 found all seven).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
});

/** The gym plan with day 3 dealt: day 2 walked to its end, the calendar moved on — the way the owner came to it. */
function recallGymDay3(object $ctx): array
{
    [$user, $token] = planLearner();
    $id = planGymLoad((string) $user->id);
    // The owner's lines of «С тренером» were bought in a voice his learner no longer has (FIX-3 §1): given the learner's
    // own voice here, BOTH pages of the sheet have files to play — and the test sees which files they play.
    $learnerMale = (string) app(App\Modules\Plan\Application\Port\LineSpeaker::class)->voiceKeyFor(
        'en', App\Modules\Plan\Domain\ValueObject\Speaker::Learner, App\Modules\Shared\Domain\ValueObject\VoiceGender::Male,
    );
    $learnerFemale = (string) app(App\Modules\Plan\Application\Port\LineSpeaker::class)->voiceKeyFor(
        'en', App\Modules\Plan\Domain\ValueObject\Speaker::Learner, App\Modules\Shared\Domain\ValueObject\VoiceGender::Female,
    );
    DB::table('plan_line_audios')->where('scene_id', '01M32DXHYGYMK0E1DBR01PYDHA')->where('voice_key', $learnerFemale)->update(['voice_key' => $learnerMale]);
    // Day 2 closed as the owner closed it: the next day dated the calendar day after day 2 was opened (GEN-3 §11).
    DB::table('plan_days')->where('plan_id', $id)->where('number', 2)->update(['status' => 'closed', 'closed_at' => '2026-09-22T12:40:00Z']);
    DB::table('plan_days')->where('plan_id', $id)->where('number', 3)->update(['opens_on' => '2026-09-23']);

    return [$token, $id, planOpenDay($ctx, $token, $id, 3)['cards']];
}

/**
 * Canon (§1): «для каждой страницы каждой строки текст записи озвучки = текст строки (без регистра и знака конца)». The
 * file a line of a page plays is the file of THAT page's scene and THAT line, and its text is the line's. CATCHES a page
 * resolved in the card's scene — the second page of the owner's sheet with the reception's seven files.
 */
it('plays every line of every page of «Вспомнить» with the file of that line of that page\'s scene', function () {
    [, $id, $cards] = recallGymDay3($this);
    $sheet = array_values(array_filter($cards, static fn (array $c): bool => $c['kind'] === 'recall_scenes'))[0];
    $files = DB::table('plan_line_audios')->get(['id', 'scene_id', 'line_ref'])->keyBy('id');
    // What each file was bought for: the line of ITS scene under ITS ref, as the lesson says it.
    $lines = [];
    $plan = app(App\Modules\Plan\Domain\Repository\PlanRepository::class)->findById(App\Modules\Plan\Domain\ValueObject\PlanId::fromString($id));
    foreach ($plan?->scenes() ?? [] as $scene) {
        foreach ($scene->lesson() === null ? [] : App\Modules\Plan\Domain\Service\SpokenLines::dialogue($scene->lesson()) as $line) {
            $lines[$scene->id()->value.':'.$line['ref']] = $line['text'];
        }
    }
    $norm = static fn (string $t): string => mb_strtolower((string) preg_replace('/[\s.!?…]+$/u', '', trim($t)));

    expect(array_column($sheet['payload']['scenes'], 'title_native'))->toBe(['Ресепшен зала', 'С тренером']);
    $played = 0;
    foreach ($sheet['payload']['scenes'] as $page) {
        foreach ($page['lines'] as $line) {
            $file = $files[basename((string) $line['audio']['url'])] ?? null;
            expect($file)->not->toBeNull("{$page['title_native']} {$line['ref']}")
                ->and($file->scene_id)->toBe($page['scene_id'])
                ->and($file->line_ref)->toBe($line['ref'])
                ->and($norm($lines[$file->scene_id.':'.$file->line_ref] ?? ''))->toBe($norm($line['text_target']));
            $played++;
        }
    }
    expect($played)->toBe(14);
});

/**
 * Canon (§1, check of ADM-1): the page of the plan, which reads the very answer the phone gets, finds no line whose sound
 * says something else. CATCHES the owner's second page — «Строка: звук играет «Do you have a day pass?» (…:x1b), а
 * строка — «I'm working on general fitness.»», seven times.
 */
it('leaves the check «звук ≠ текст» of the plan page nothing to find on the rehearsal', function () {
    recallGymDay3($this);
    app('auth')->forgetGuards();
    [, $admin] = adminActor();

    $found = collect($this->withHeader('Authorization', "Bearer {$admin}")->getJson('/admin/api/plans/2DX8QC/issues?day=3')->assertOk()->json('data'))
        ->where('check', 'sound_text_mismatch')->values()->all();

    expect($found)->toBe([]);
});
