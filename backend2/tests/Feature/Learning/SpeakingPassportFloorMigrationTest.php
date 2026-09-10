<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Exercises the data migration directly — {@see \App\Modules\Learning\Domain\Service\ModePassport}
 * and the write-side 422 guard are covered elsewhere; this proves the one-off backfill that
 * corrects a row already sitting below speaking's floor (found on the dev database at `learning`)
 * is itself correct and, per CLAUDE.md, genuinely reversible rather than reversible-in-name-only.
 */
it('corrects a speaking row below the passport floor and restores it on rollback', function () {
    $path = base_path('app/Modules/Learning/Infrastructure/Migration/2026_08_19_090000_fix_speaking_below_passport_floor.php');
    $migration = require $path;

    // The real migration already ran (RefreshDatabase) and left its (empty) backup table behind;
    // drop it via the migration's own down() so this test's up() can create it fresh, exactly as
    // it would on a database that had never seen this migration before.
    $migration->down();

    DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->update([
        'min_acquisition' => 'learning',
        'min_learning_step' => 1,
        'min_successful_reviews' => null,
    ]);

    $migration->up();

    $fixed = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->first();
    expect($fixed->min_acquisition)->toBe('graduated')
        ->and($fixed->min_learning_step)->toBeNull()
        ->and($fixed->min_successful_reviews)->toBeNull();

    $migration->down();

    $restored = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->first();
    expect($restored->min_acquisition)->toBe('learning')
        ->and($restored->min_learning_step)->toBe(1)
        ->and($restored->min_successful_reviews)->toBeNull();

    expect(DB::getSchemaBuilder()->hasTable('learning_mode_settings_speaking_floor_backup'))->toBeFalse();
});

it('leaves a speaking row already at or above the floor untouched', function () {
    $path = base_path('app/Modules/Learning/Infrastructure/Migration/2026_08_19_090000_fix_speaking_below_passport_floor.php');
    $migration = require $path;
    $migration->down();

    $before = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->first();
    expect($before->min_acquisition)->toBe('graduated'); // shipped default, untouched by prior test

    $migration->up();

    $after = DB::table('learning_mode_settings')->whereNull('user_id')->where('mode', 'speaking')->first();
    expect($after->min_acquisition)->toBe('graduated')
        ->and($after->updated_at)->toEqual($before->updated_at);

    $migration->down();
});
