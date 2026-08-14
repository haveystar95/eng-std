<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** The reader memoises, but every write invalidates it — so one shared instance is honest here. */
function modes(): EnabledModesReader
{
    return app(EnabledModesReader::class);
}

/** @param list<ExerciseMode> $modes */
function setGlobal(array $modes): void
{
    app(EnabledModesWriter::class)->setGlobalDefault(new EnabledModes($modes));
}

/** @return list<string> */
function wireModes(EnabledModes $modes): array
{
    return array_map(static fn (ExerciseMode $m): string => $m->value, $modes->modes);
}

it('seeds the global default from the config the app was running on', function () {
    // The migration seeds it, so moving the source of truth into the database changes nothing
    // until someone flips a toggle. Zero regression is the whole point of this slice.
    expect(wireModes(modes()->globalDefault()))->toBe(config('learning.enabled_modes'));
});

it('gives a user the global default while they have no override', function () {
    [$user] = learner();

    expect(modes()->overrideFor(UserId::fromString($user->id)))->toBeNull()
        ->and(wireModes(modes()->forUser(UserId::fromString($user->id))))
        ->toBe(config('learning.enabled_modes'));
});

it('follows the global default as it changes, rather than a copy taken at signup', function () {
    [$user] = learner();
    setGlobal([ExerciseMode::MultipleChoice, ExerciseMode::Typing]);

    expect(wireModes(modes()->forUser(UserId::fromString($user->id))))->toBe(['multiple_choice', 'typing']);
});

it('lets one user override the default without touching anyone else', function () {
    [$alice] = learner();
    [$bob] = learner();
    $aliceId = UserId::fromString($alice->id);

    app(EnabledModesWriter::class)->setOverrideFor($aliceId, new EnabledModes([ExerciseMode::Scramble]));

    expect(wireModes(modes()->forUser($aliceId)))->toBe(['scramble'])
        ->and(wireModes(modes()->forUser(UserId::fromString($bob->id))))->toBe(config('learning.enabled_modes'));
});

it('preserves the ORDER of an override — it drives the practice rotation', function () {
    [$user] = learner();
    $id = UserId::fromString($user->id);
    $ordered = [ExerciseMode::Typing, ExerciseMode::MultipleChoice, ExerciseMode::Listening];

    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes($ordered));

    expect(wireModes(modes()->forUser($id)))->toBe(['typing', 'multiple_choice', 'listening']);
});

it('drops an override back to inheriting, instead of freezing a copy of today default', function () {
    [$user] = learner();
    $id = UserId::fromString($user->id);
    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes([ExerciseMode::Typing]));

    app(EnabledModesWriter::class)->setOverrideFor($id, null);

    expect(modes()->overrideFor($id))->toBeNull()
        ->and(DB::table('learning_mode_settings')->where('user_id', $user->id)->count())->toBe(0);

    // …and "inherit" means it now follows a LATER change to the default.
    setGlobal([ExerciseMode::MultipleChoice, ExerciseMode::Cloze]);
    expect(wireModes(modes()->forUser($id)))->toBe(['multiple_choice', 'cloze']);
});

it('rewrites the same rows instead of accumulating them', function () {
    [$user] = learner();
    $id = UserId::fromString($user->id);
    $perScope = count(ExerciseMode::cases()); // one row per (scope, mode) since the matrix moved in

    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes([ExerciseMode::Typing]));
    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes([ExerciseMode::Listening]));
    setGlobal([ExerciseMode::Typing]);
    setGlobal([ExerciseMode::Cloze]);

    expect(DB::table('learning_mode_settings')->count())->toBe($perScope * 2) // one scope global, one override
        ->and(wireModes(modes()->forUser($id)))->toBe(['listening']);
});

it('skips a stored mode this build does not know, instead of failing every card', function () {
    [$user] = learner();
    // A row written by a newer deploy, read after a rollback.
    DB::table('learning_mode_settings')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id,
        'mode' => 'time_travel',
        'enabled' => true,
        'position' => 0,
        'min_acquisition' => 'graduated',
        'options_policy' => 'standard',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(EnabledModesWriter::class)->setOverrideFor(UserId::fromString($user->id), new EnabledModes([
        ExerciseMode::Typing, ExerciseMode::Listening,
    ]));

    expect(wireModes(modes()->forUser(UserId::fromString($user->id))))->toBe(['typing', 'listening']);
});

it('erases a user override with the account', function () {
    [$user] = learner();
    app(EnabledModesWriter::class)->setOverrideFor(UserId::fromString($user->id), new EnabledModes([ExerciseMode::Typing]));

    DB::table('users')->where('id', $user->id)->delete();

    expect(DB::table('learning_mode_settings')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('learning_mode_settings')->whereNull('user_id')->count())->toBe(count(ExerciseMode::cases()));
});

// ── the admission matrix, now stored beside the toggles ──────────────────────

it('seeds the shipped admission matrix, so moving it into the database changes nothing', function () {
    $matrix = app(\App\Modules\Learning\Application\Port\ModeAdmissionReader::class)->globalMatrix();

    expect($matrix->toWire())->toBe(\App\Modules\Learning\Domain\ValueObject\ModeAdmission::shipped()->toWire());
});

it('lets one user be admitted to a trainer earlier than everyone else', function () {
    [$alice] = learner();
    [$bob] = learner();
    $aliceId = UserId::fromString($alice->id);
    $admission = app(\App\Modules\Learning\Application\Port\ModeAdmissionReader::class);

    // Typing normally waits for four post-graduation reviews; give Alice it on graduation.
    app(\App\Modules\Learning\Application\Command\SetModeAdmissionHandler::class)(
        new \App\Modules\Learning\Application\Command\SetModeAdmission(
            userId: $aliceId, mode: 'typing', minAcquisition: 'graduated', minReps: null,
        ),
    );

    expect($admission->matrixFor($aliceId)->allows(ExerciseMode::Typing, 3))->toBeTrue()
        ->and($admission->matrixFor(UserId::fromString($bob->id))->allows(ExerciseMode::Typing, 3))->toBeFalse()
        // …and the product default is untouched.
        ->and($admission->globalMatrix()->allows(ExerciseMode::Typing, 3))->toBeFalse();
});

it('does not switch a trainer on just because its threshold was written', function () {
    // The two settings are independent: moving a rung must not turn a mode on for a user who was
    // not being dealt it, which is how a "make typing earlier" tweak would quietly enable dictation.
    [$user] = learner();
    $id = UserId::fromString($user->id);
    setGlobal([ExerciseMode::MultipleChoice]);

    app(\App\Modules\Learning\Application\Command\SetModeAdmissionHandler::class)(
        new \App\Modules\Learning\Application\Command\SetModeAdmission(
            userId: $id, mode: 'dictation', minAcquisition: 'graduated',
        ),
    );

    expect(wireModes(modes()->forUser($id)))->toBe(['multiple_choice']);
});

it('refuses a reps threshold on a rung the pair reaches before graduating', function () {
    // reps is counted FROM graduation, so it says nothing on a recognition rung; storing one there
    // would read as a stricter rule than it is.
    expect(fn () => app(\App\Modules\Learning\Application\Command\SetModeAdmissionHandler::class)(
        new \App\Modules\Learning\Application\Command\SetModeAdmission(
            userId: null, mode: 'typing', minAcquisition: 'learning', minLearningStep: 1, minReps: 3,
        ),
    ))->toThrow(InvalidArgumentException::class);
});
