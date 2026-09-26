<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\RescueKits;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * THE RESCUE KIT BY THE PAIR (наряд LANG-1b §2): «сейчас набор en←ru раздаётся любой паре (немцу — английские фразы). Набор
 * живёт в пакете целевого языка: 6 реплик спасения на цели с переводами на все девять родных. Озвучка — голосом ученика цели
 * при первой выдаче, файл кэшируется по ключу (цель, пол, строка). Тест: пара ru→de получает немецкий набор с русскими
 * переводами; be→en — английский с белорусскими.»
 */

/** @return array<string, mixed> the plan of a learner of `$native` for `$target`, as the client reads it */
function rkPlan(object $ctx, string $native, string $target): array
{
    // A second learner in one test: the guard keeps the first one it resolved unless told to forget.
    app('auth')->forgetGuards();
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => $native]);
    $id = planCreate($ctx, $token, ['days_total' => 1, 'target_lang' => $target])['id'];

    return planRead($ctx, $token, $id);
}

// CATCHES the English-Russian list handed to every pair again, a kit in the learner's language instead of the target's,
// a translation into the wrong learner's language, and the reading of the old list still sent.
it('hands a ru→de plan the German kit with Russian translations, and a be→en plan the English kit with Belarusian ones', function () {
    $de = rkPlan($this, 'ru', 'de');
    $en = rkPlan($this, 'be', 'en');

    expect(array_column($de['rescue_kit'], 'text_target'))->toBe([
        'Wie bitte?', 'Können Sie bitte langsamer sprechen?', 'Ich verstehe nicht.', 'Einen Moment.', 'Können Sie mir das aufschreiben?', 'Danke.',
    ])->and(array_column($de['rescue_kit'], 'text_native'))->toBe([
        'Простите?', 'Можно помедленнее, пожалуйста?', 'Я не понимаю.', 'Одну минуту.', 'Можете это записать?', 'Спасибо.',
    ])->and(array_column($en['rescue_kit'], 'text_target'))->toBe([
        'Sorry?', 'Could you say that more slowly, please?', "I don't understand.", 'One moment.', 'Can you write it down?', 'Thank you.',
    ])->and(array_column($en['rescue_kit'], 'text_native'))->toBe([
        'Прабачце?', 'Можна павольней, калі ласка?', 'Я не разумею.', 'Хвілінку.', 'Можаце гэта запісаць?', 'Дзякуй.',
    ])
        // The voice is off in the suite: the kit goes out without its sound, and without the old list's reading.
        ->and(array_unique(array_column($de['rescue_kit'], 'audio_url')))->toBe([null])
        ->and(array_unique(array_column($de['rescue_kit'], 'pronunciation_native')))->toBe([null])
        ->and(array_keys($de['rescue_kit'][0]))->toBe(['text_target', 'text_native', 'pronunciation_native', 'audio_url']);
});

// «Озвучка — голосом ученика цели при первой выдаче, файл кэшируется по ключу (цель, пол, строка)». The voice job of a plan
// buys the kit's lines with its scene's, in the learner's voice. CATCHES a kit never voiced, a kit bought again for every
// plan of the same target and gender, a kit bought in the partner's voice, and a line whose file the API does not serve.
it('says the kit in the learner\'s voice once, and every later plan of that target and gender reads the same files', function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    $vendor = new FakeSpeechSynthesizer;
    app()->instance(SpeechSynthesizerPort::class, $vendor);
    $kit = ['Wie bitte?', 'Können Sie bitte langsamer sprechen?', 'Ich verstehe nicht.', 'Einen Moment.', 'Können Sie mir das aufschreiben?', 'Danke.'];
    $asked = static fn (): array => array_values(array_filter(array_map(static fn ($line): string => $line->text, $vendor->lines), static fn (string $t): bool => in_array($t, $kit, true)));

    $first = rkPlan($this, 'ru', 'de');
    $once = $asked();
    $second = rkPlan($this, 'uk', 'de');
    $voice = (string) app(LineSpeaker::class)->voiceKeyFor('de', Speaker::Learner, VoiceGender::Male);
    $learnerVoice = array_values(array_unique(array_map(
        static fn ($line): string => $line->voice->key().':'.$line->voice->variant(),
        array_filter($vendor->lines, static fn ($line): bool => in_array($line->text, $kit, true)),
    )));

    expect($once)->toBe($kit)
        // The second plan — another learner, the same target and gender — buys its day, not the kit again.
        ->and($asked())->toBe($kit)
        ->and($learnerVoice)->toBe([$voice])
        ->and(array_column($first['rescue_kit'], 'audio_url'))->each->toContain('/api/v1/plans/rescue-audio/')
        ->and(array_column($second['rescue_kit'], 'audio_url'))->toBe(array_column($first['rescue_kit'], 'audio_url'))
        ->and(array_column($first['rescue_kit'], 'audio_url')[0])->toEndWith('/'.RescueKits::audioKey('de', VoiceGender::Male, $voice, 'Wie bitte?'));

    app('auth')->forgetGuards();
    [, $token] = planLearner();
    $this->withHeader('Authorization', "Bearer {$token}")->get(parse_url((string) $first['rescue_kit'][0]['audio_url'], PHP_URL_PATH))
        ->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    $this->withHeader('Authorization', "Bearer {$token}")->get('/api/v1/plans/rescue-audio/'.str_repeat('0', 40))->assertNotFound();
});

// CATCHES a kit filed by the target alone — a woman's plan reading a man's voice.
it('buys the kit again in the other gender\'s voice', function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer);
    $man = rkPlan($this, 'ru', 'de');
    app('auth')->forgetGuards();
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['gender' => 'female']);
    $woman = planRead($this, $token, planCreate($this, $token, ['days_total' => 1, 'target_lang' => 'de'])['id']);

    expect(array_filter(array_column($woman['rescue_kit'], 'audio_url')))->toHaveCount(6)
        ->and(array_intersect(array_column($woman['rescue_kit'], 'audio_url'), array_column($man['rescue_kit'], 'audio_url')))->toBe([]);
});
