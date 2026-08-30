<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\PlanModeSettingsReader;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The plan's own scope of `learning_mode_settings` — that it is seeded, that it reads back, and
 * above all that it stays OUT of the ordinary trainer settings that share the table.
 */
it('seeds one plan row per level and plan-ladder trainer', function () {
    $expected = count(PlanLevel::cases()) * count(PlanStageLadder::allModes());

    expect(DB::table('learning_mode_settings')->where('scope', 'plan')->count())->toBe($expected)
        ->and(DB::table('learning_mode_settings')->where('scope', 'plan')->whereNull('level')->count())->toBe(0);
});

it('reads a level’s six knobs back, merged from its per-mode rows', function () {
    $knobs = app(PlanModeSettingsReader::class)->knobsFor(PlanLevel::Zero);

    expect($knobs->toArray())->toBe([
        'mc_options' => 3,
        'distractor_closeness' => 'far',
        'cloze_blanks' => 1,
        'bank_extra' => 0,
        'typing_hint' => 'first_letter',
        'tts_rate' => 'slow',
    ]);
});

it('keeps the plan rows out of the ordinary trainer registry', function () {
    // The global reader must see the ten-or-so global rows and nothing else: a plan row leaking in
    // would appear as a second `speaking` in the rotation and as a matrix rule nobody wrote.
    $rows = app(EnabledModesReader::class);
    $global = DB::table('learning_mode_settings')->where('scope', 'global')->count();

    expect($global)->toBe(count(ExerciseMode::cases()))
        ->and(app(EnabledModesReader::class)->globalDefault()->modes)
        ->each->toBeInstanceOf(ExerciseMode::class)
        ->and($rows->globalDefault()->modes)->toHaveCount(
            DB::table('learning_mode_settings')->where('scope', 'global')->where('enabled', true)->count(),
        );
});

it('reports a level’s open trainers, and closes one when its row is switched off', function () {
    $reader = app(PlanModeSettingsReader::class);
    expect($reader->openModesFor(PlanLevel::Basic))->toHaveCount(count(PlanStageLadder::allModes()));

    DB::table('learning_mode_settings')
        ->where('scope', 'plan')->where('level', 'basic')->where('mode', 'dictation')
        ->update(['enabled' => false]);

    // A fresh instance: the reader memoises per request, exactly like the global one.
    $open = (new App\Modules\Learning\Infrastructure\Eloquent\EloquentPlanModeSettingsReader())->openModesFor(PlanLevel::Basic);

    expect($open)->not->toContain(ExerciseMode::Dictation)
        ->and($open)->toContain(ExerciseMode::Typing);
});

it('refuses a plan row without a level, and a global row with one', function () {
    expect(fn () => DB::table('learning_mode_settings')->insert([
        'id' => (string) App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => null, 'scope' => 'plan', 'level' => null, 'mode' => 'typing',
        'enabled' => true, 'position' => 0, 'min_acquisition' => 'new', 'options_policy' => 'standard',
        'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
