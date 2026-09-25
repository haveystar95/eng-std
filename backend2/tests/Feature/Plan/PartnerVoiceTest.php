<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\SpeechLine;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\LineToSay;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Infrastructure\Adapter\GenerationLineSpeaker;
use App\Modules\Plan\Infrastructure\Eloquent\PartnerVoiceBackfill;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE SECOND PARTNER VOICE, AS THE SERVER CASTS AND KEEPS IT (наряд FIX-4c §1): a scene's partner voice is fixed once, when
 * its lesson is accepted, by the rota of the plan's scenes; the scenes that were there before are given theirs by the
 * migration's backfill.
 */

/** @return array{F1: string, F2: string, M1: string, M2: string} the pack's partner voices */
function pvVoices(): array
{
    $pack = (array) config('generation.speech.voices.en.partner');

    return ['F1' => (string) $pack['female']['voice'], 'F2' => (string) $pack['female_2']['voice'], 'M1' => (string) $pack['male']['voice'], 'M2' => (string) $pack['male_2']['voice']];
}

/**
 * A plan of four scenes — its three and one spliced in fourth, a nurse — every lesson unwritten again. `$overrides` —
 * what `POST /plans` is given besides the defaults: `target_lang` for a plan in another language (наряд LANG-1, п. 9).
 *
 * @param  array<string, mixed>  $overrides
 * @return list<string> the scene ids in the plan's order
 */
function pvFourScenes(object $ctx, array $overrides = []): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, $overrides)['id'];
    $third = (string) DB::table('plan_scenes')->where('plan_id', $id)->where('order', 3)->value('id');
    $columns = array_values(array_filter(Schema::getColumnListing('plan_scenes'), static fn (string $c): bool => ! in_array($c, ['id', 'order', 'partner_role_target', 'partner_role_native'], true)));
    $quoted = implode(', ', array_map(static fn (string $c): string => '"'.$c.'"', $columns));
    DB::insert("INSERT INTO plan_scenes (id, \"order\", partner_role_target, partner_role_native, {$quoted}) SELECT ?, 4, 'Nurse', 'Медсестра', {$quoted} FROM plan_scenes WHERE id = ?", [Ulid::generate(), $third]);
    DB::table('plan_scenes')->where('plan_id', $id)->update(['lesson_status' => 'pending', 'build_started_at' => null, 'partner_voice_gender' => null, 'partner_voice_id' => null]);

    return DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->pluck('id')->map(static fn ($s): string => (string) $s)->all();
}

// Canon (§1): «голос сцены фиксируется ОДИН раз … сцены одного пола в порядке плана чередуются между голосом 1 и 2»; «план
// с ролями Ж, Ж, М, Ж → голоса F1, F2, M1, F1» — through the lesson build as production runs it, the role's gender being
// the lesson's (решение приёмки FIX-4c: голос — при приёме урока). CATCHES the registrar and the doctor cast one voice, a
// voice cast before the gender is known, and a voice recast when the lesson is written again.
it('casts each scene\'s partner voice when its lesson is accepted: roles F, F, M, F speak F1, F2, M1, F1', function () {
    $genders = ['Receptionist' => 'female', 'Doctor' => 'female', 'Pharmacist' => 'male', 'Nurse' => 'female'];
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function (LessonRequest $request) use ($genders): array {
        return ['role_gender' => $genders[$request->roles->partnerTarget] ?? 'female'] + planCleanLesson($request);
    }));
    $scenes = pvFourScenes($this);
    $voices = pvVoices();
    $cast = static fn (): array => DB::table('plan_scenes')->whereIn('id', $scenes)->orderBy('order')->pluck('partner_voice_id')->all();

    foreach ($scenes as $scene) {
        app(BuildLessonHandler::class)(new BuildLesson(PlanSceneId::fromString($scene)));
    }

    expect(DB::table('plan_scenes')->whereIn('id', $scenes)->orderBy('order')->pluck('partner_voice_gender')->all())->toBe(['female', 'female', 'male', 'female'])
        ->and($cast())->toBe([$voices['F1'], $voices['F2'], $voices['M1'], $voices['F1']]);

    // Written again (a failed build retried): the voice it has is kept — a scene is cast once.
    DB::table('plan_scenes')->where('id', $scenes[1])->update(['lesson_status' => 'pending']);
    app(BuildLessonHandler::class)(new BuildLesson(PlanSceneId::fromString($scenes[1])));
    expect($cast())->toBe([$voices['F1'], $voices['F2'], $voices['M1'], $voices['F1']]);
});

/** A stored file of a scene's partner line in a voice. */
function pvVoiced(string $sceneId, string $voice): void
{
    DB::table('plan_line_audios')->insert([
        'id' => Ulid::generate(), 'scene_id' => $sceneId, 'user_id' => (string) DB::table('plan_scenes')->where('id', $sceneId)->value('user_id'),
        'voice_key' => "elevenlabs:eleven_v3_conversational:{$voice}:s50", 'format' => 'mp3', 'path' => 'x.mp3', 'bytes' => 1, 'line_ref' => 'x1',
    ]);
}

// Canon (§1): «существующие планы: бэкфилл partner_voice_id = голос 1 своего пола (то, чем они уже озвучены); переозвучки
// нет» — and the live rehearsal's «сцена 1 = F1, сцена 2 = F2» on a stand whose scenes were never voiced: voice 1 where
// there is sound, the rule where there is none, nothing where there is no lesson (отчёт FIX-4c §1). CATCHES a voiced scene
// moved off the voice its files are in, two unvoiced women left in one voice, a lesson-less scene cast before its gender,
// and a second run that changes anything.
it('backfills the scenes that were there: voice 1 where the partner is voiced, the rota where not, none without a lesson', function () {
    $scenes = pvFourScenes($this);
    $voices = pvVoices();
    DB::table('plan_scenes')->whereIn('id', array_slice($scenes, 0, 3))->update(['lesson_status' => 'ready']);
    DB::table('plan_scenes')->where('id', $scenes[1])->update(['partner_voice_gender' => 'female']);
    DB::table('plan_scenes')->where('id', $scenes[2])->update(['partner_voice_gender' => 'female']);
    // The first: written before voices had genders — the default cast, a woman; voiced in her voice.
    pvVoiced($scenes[0], $voices['F1']);
    $cast = static fn (): array => DB::table('plan_scenes')->whereIn('id', $scenes)->orderBy('order')->pluck('partner_voice_id')->all();

    expect(app(PartnerVoiceBackfill::class)->run())->toBe(['voiced' => 1, 'cast' => 2, 'left' => 1])
        ->and($cast())->toBe([$voices['F1'], $voices['F2'], $voices['F1'], null])
        ->and(app(PartnerVoiceBackfill::class)->run())->toBe(['voiced' => 0, 'cast' => 0, 'left' => 1])
        ->and($cast())->toBe([$voices['F1'], $voices['F2'], $voices['F1'], null]);

    // A voiced scene after an unvoiced one keeps its sound even when that makes two neighbours one voice: nothing is voiced
    // again (the one limit of the backfill, and only for scenes voiced before the second voice existed).
    DB::table('plan_scenes')->whereIn('id', $scenes)->update(['partner_voice_id' => null]);
    DB::table('plan_line_audios')->delete();
    pvVoiced($scenes[1], $voices['F1']);
    app(PartnerVoiceBackfill::class)->run();
    expect($cast())->toBe([$voices['F1'], $voices['F1'], $voices['F2'], null]);
});

/**
 * The pack's German partner rows told apart from the English ones — the deployment gives both the same ids until `.env`
 * names German its own (наряд LANG-1, п. 9), and a cast from the wrong language's rows would look right. The catalog is a
 * singleton built from the config, so it is built again.
 *
 * @return array{F1: string, F2: string, M1: string, M2: string}
 */
function pvGermanVoices(): array
{
    $ids = ['F1' => 'de-woman-1', 'F2' => 'de-woman-2', 'M1' => 'de-man-1', 'M2' => 'de-man-2'];
    config([
        'generation.speech.voices.de.partner.female.voice' => $ids['F1'],
        'generation.speech.voices.de.partner.female_2.voice' => $ids['F2'],
        'generation.speech.voices.de.partner.male.voice' => $ids['M1'],
        'generation.speech.voices.de.partner.male_2.voice' => $ids['M2'],
    ]);
    app()->forgetInstance(VoiceCatalog::class);

    return $ids;
}

// Canon (наряд LANG-1, п. 9; DECISIONS пп. 318, 414): «голоса — по языку обучения: шесть строк у каждой цели плана»; «план
// на de получает голос сцены из строк de». CATCHES a scene of a German plan cast from the English rows (the catalog asked
// in the account's language, or in a hard-wired `en`), and a German plan left without a voice because its pack has no rows.
//
// The row is about the VOICE, not about German: the fake's lesson is English text, and the real German pack (наряд LANG-1,
// the language executors) would read it by German rules and fail the day before a voice is cast. So the German lessons
// here are checked by the English pack under the code `de` — a binding of this test alone — and the build, the gate and
// the cast run as production runs them; what German a German lesson must be is the German pack's own tests' business.
it('casts a German plan\'s partner voices from the German rows of the pack: roles F, F, M, F speak de F1, F2, M1, F1', function () {
    $genders = ['Receptionist' => 'female', 'Doctor' => 'female', 'Pharmacist' => 'male', 'Nurse' => 'female'];
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function (LessonRequest $request) use ($genders): array {
        return ['role_gender' => $genders[$request->roles->partnerTarget] ?? 'female'] + planCleanLesson($request);
    }));
    app()->instance(LanguagePacks::class, new LanguagePacks(['de' => config('lesson.lang.en')] + (array) config('lesson.lang', [])));
    $voices = pvGermanVoices();
    $scenes = pvFourScenes($this, ['target_lang' => 'de']);

    foreach ($scenes as $scene) {
        app(BuildLessonHandler::class)(new BuildLesson(PlanSceneId::fromString($scene)));
    }

    expect(DB::table('plans')->where('id', DB::table('plan_scenes')->where('id', $scenes[0])->value('plan_id'))->value('target_lang'))->toBe('de')
        ->and(DB::table('plan_scenes')->whereIn('id', $scenes)->orderBy('order')->pluck('lesson_status')->all())->toBe(['ready', 'ready', 'ready', 'ready'])
        ->and(DB::table('plan_scenes')->whereIn('id', $scenes)->orderBy('order')->pluck('partner_voice_id')->all())
        ->toBe([$voices['F1'], $voices['F2'], $voices['M1'], $voices['F1']]);
});

// Canon (наряд LANG-1, п. 9): «GenerationLineSpeaker передаёт language_code = цель плана в каждой строке; ключ голоса не
// меняется». CATCHES a German line bought without its language (the vendor guessing it from the letters), the language
// taken from somewhere else than the plan's target, an upper-case code the vendor would refuse, and a voice key that
// grew the language — which would leave every file bought before LANG-1 unread (DECISIONS п. 248).
it('asks the vendor for a German plan\'s lines in German, in the German rows\' voices, under keys without the language', function () {
    $voices = pvGermanVoices();
    $fake = new FakeSpeechSynthesizer;
    $speaker = new GenerationLineSpeaker($fake, app(VoiceCatalog::class), true);
    $kept = [];

    $speaker->sayEach('DE', [
        new LineToSay('x1', 'Wo tut es weh?', Speaker::Partner, VoiceGender::Female, $voices['F2']),
        new LineToSay('x1b', 'Hier, im unteren Rücken.', Speaker::Learner, VoiceGender::Male),
    ], static function (string $ref, SpokenAudio $audio) use (&$kept): void {
        $kept[$ref] = $audio->voiceKey;
    });

    $learner = (string) config('generation.speech.voices.de.learner.male.voice');
    expect(array_map(static fn (SpeechLine $l): ?string => $l->languageCode, $fake->lines))->toBe(['de', 'de'])
        ->and(array_map(static fn (SpeechLine $l): string => $l->voice->voice, $fake->lines))->toBe([$voices['F2'], $learner])
        ->and($kept)->toBe([
            'x1' => "elevenlabs:eleven_v3_conversational:{$voices['F2']}:s50",
            'x1b' => "elevenlabs:eleven_v3_conversational:{$learner}:s50",
        ])
        ->and($speaker->voiceKeyFor('de', Speaker::Partner, VoiceGender::Female, $voices['F2']))->toBe($kept['x1']);

    // An English line is said as it always was, only named: the same voice under the same key as before LANG-1.
    $fake->lines = [];
    $speaker->sayEach('en', [new LineToSay('x2', 'Where does it hurt?', Speaker::Partner, VoiceGender::Female)], static function (string $ref, SpokenAudio $audio) use (&$kept): void {
        $kept[$ref] = $audio->voiceKey;
    });
    $english = (string) config('generation.speech.voices.en.partner.female.voice');
    expect($fake->lines[0]->languageCode)->toBe('en')
        ->and($fake->lines[0]->voice->voice)->toBe($english)
        ->and($kept['x2'])->toBe("elevenlabs:eleven_v3_conversational:{$english}:s50");
});
