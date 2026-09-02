<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * THE BACKFILL, AND THE WAY BACK — миграция полок и ярусов (наряд P2-v0.4).
 *
 * Two questions, and a plan already running depends on both. Does a day written under the old shape
 * come out of this migration with the shelves its cards actually had? And can the migration be
 * undone on a database that has since been used, without taking a learner's history with it?
 *
 * The second is the harder one and the reason `down()` is not a `dropColumn` and nothing else: by
 * the time anybody rolls back, `numbers` cards exist, and the CHECK constraint they are going back
 * to has no `number` in it. The rollback turns those cards into ordinary ones rather than deleting
 * them — a card that reads as a plain line is a smaller loss than a review log with holes.
 */
function shelfMigration(): object
{
    return require base_path('app/Modules/Vocabulary/Infrastructure/Migration/2026_09_02_120000_add_shelf_and_tier_to_terms.php');
}

/** One term row, written straight — the migration is being tested, not the writers above it. */
function rawTerm(string $text, ?string $kind, ?string $speaker = null): string
{
    $id = (string) Symfony\Component\Uid\Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id,
        'lang' => 'en',
        'text' => $text,
        'normalized_text' => mb_strtolower($text),
        'type' => $kind === 'word' ? 'word' : 'phrase',
        'kind' => $kind,
        'speaker' => $speaker,
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

it('gives every card of an already-written day the shelf its old shape implies', function () {
    $migration = shelfMigration();

    // Roll the day back to before this наряд, then write a day the way v0.3 wrote one.
    $migration->down();

    $roleLine = rawTerm('How long has it been like this?', 'line', 'role');
    $ownLine = rawTerm('It hurts in my lower back.', 'line', 'learner');
    $unmarkedLine = rawTerm('I need to check in, please.', 'line');
    $word = rawTerm('prescription', 'word');
    $chunk = rawTerm('take a seat', 'chunk');
    $ordinary = rawTerm('apple', null);

    $migration->up();

    $shelves = DB::table('terms')->pluck('shelf', 'id');
    $tiers = DB::table('terms')->pluck('tier', 'id');

    // THE INTERLOCUTOR'S LINE IS UNDERSTOOD, everything the learner says is produced, and a term
    // that never came from a plan day is left alone rather than guessed at.
    expect($shelves[$roleLine])->toBe('hear')
        ->and($tiers[$roleLine])->toBe('understand')
        ->and($shelves[$ownLine])->toBe('say')
        ->and($tiers[$ownLine])->toBe('speak')
        ->and($shelves[$unmarkedLine])->toBe('say')
        ->and($shelves[$word])->toBe('words')
        ->and($shelves[$chunk])->toBe('chunks')
        ->and($tiers[$chunk])->toBe('speak')
        ->and($shelves[$ordinary])->toBeNull()
        ->and($tiers[$ordinary])->toBeNull();
});

it('rolls back a database that has been used, and keeps the cards it cannot describe', function () {
    $migration = shelfMigration();

    $line = rawTerm('Please come to the front desk.', 'line', 'role');
    $number = rawTerm('Your appointment is at four fifteen.', 'number');
    DB::table('terms')->where('id', $number)->update([
        'shelf' => 'numbers', 'tier' => 'understand', 'skill_ref' => 's1.3', 'number_value' => '4:15',
    ]);

    $migration->down();

    // The columns are gone, and so is the value of the `number` kind — but not the row, and not the
    // reviews hanging off it. A learner's history outlives a schema change in both directions.
    expect(Schema::hasColumn('terms', 'shelf'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'tier'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'skill_ref'))->toBeFalse()
        ->and(Schema::hasColumn('terms', 'number_value'))->toBeFalse()
        ->and(DB::table('terms')->where('id', $number)->value('kind'))->toBeNull()
        ->and(DB::table('terms')->where('id', $number)->exists())->toBeTrue()
        ->and(DB::table('terms')->where('id', $line)->value('kind'))->toBe('line');

    // …and the old CHECK is back with it, which is WHY the row had to be emptied rather than left:
    // `number` is not a kind the rollback target will accept. Read rather than provoked — a refused
    // statement poisons the surrounding transaction, and the `up()` below has to run in it.
    $kindCheck = DB::scalar(
        "SELECT pg_get_constraintdef(oid) FROM pg_constraint WHERE conname = 'terms_kind_check'",
    );
    expect($kindCheck)->toContain("'line'")
        ->and($kindCheck)->toContain("'chunk'")
        ->and($kindCheck)->not->toContain("'number'");

    // Forward again — the migration is not a one-way door, and the second `up()` finds the columns
    // absent and the constraints gone exactly as the first one did.
    $migration->up();

    expect(Schema::hasColumn('terms', 'shelf'))->toBeTrue()
        ->and(DB::table('terms')->where('id', $line)->value('shelf'))->toBe('hear');
});

it('refuses a shelf and a tier it does not know', function () {
    $term = rawTerm('walk-in clinic', 'chunk');

    expect(fn () => DB::table('terms')->where('id', $term)->update(['shelf' => 'заметки']))
        ->toThrow(Illuminate\Database\QueryException::class)
        ->and(fn () => DB::table('terms')->where('id', $term)->update(['tier' => 'write']))
        ->toThrow(Illuminate\Database\QueryException::class);
});
