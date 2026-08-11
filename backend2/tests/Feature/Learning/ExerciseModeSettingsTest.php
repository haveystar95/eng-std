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

/** The reader is memoised per instance, so a test that writes must ask a fresh one. */
function modes(): EnabledModesReader
{
    app()->forgetInstance(EnabledModesReader::class);

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

it('overwrites the same scope row instead of accumulating rows', function () {
    [$user] = learner();
    $id = UserId::fromString($user->id);

    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes([ExerciseMode::Typing]));
    app(EnabledModesWriter::class)->setOverrideFor($id, new EnabledModes([ExerciseMode::Listening]));
    setGlobal([ExerciseMode::Typing]);
    setGlobal([ExerciseMode::Cloze]);

    expect(DB::table('learning_mode_settings')->count())->toBe(2) // one global, one override
        ->and(wireModes(modes()->forUser($id)))->toBe(['listening']);
});

it('skips a stored mode this build does not know, instead of failing every card', function () {
    [$user] = learner();
    // A row written by a newer deploy, read after a rollback.
    DB::table('learning_mode_settings')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id,
        'modes' => json_encode(['typing', 'time_travel', 'listening']),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(wireModes(modes()->forUser(UserId::fromString($user->id))))->toBe(['typing', 'listening']);
});

it('erases a user override with the account', function () {
    [$user] = learner();
    app(EnabledModesWriter::class)->setOverrideFor(UserId::fromString($user->id), new EnabledModes([ExerciseMode::Typing]));

    DB::table('users')->where('id', $user->id)->delete();

    expect(DB::table('learning_mode_settings')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('learning_mode_settings')->whereNull('user_id')->count())->toBe(1);
});
