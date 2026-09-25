<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Eloquent\PartnerVoiceBackfill;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\Ulid;
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
 * A plan of four scenes — its three and one spliced in fourth, a nurse — every lesson unwritten again.
 *
 * @return list<string> the scene ids in the plan's order
 */
function pvFourScenes(object $ctx): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token)['id'];
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
