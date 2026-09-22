<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\DayPace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * THE PRICE LIST OF A PLAN'S DAYS (наряд FIX-3 §2): the prices live in the config, measured on the phone; a plan keeps a
 * snapshot of them (`plans.pace`) from the moment it is made; `plan:repace` gives plans the config's list again —
 * idempotent, and it says what the open days' minutes were and are.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** @return array<string, int>|null the plan's snapshot as stored */
function ppStored(string $planId): ?array
{
    $raw = DB::table('plans')->where('id', $planId)->value('pace');

    return $raw === null ? null : json_decode((string) $raw, true);
}

/** @return int|null «≈ N мин» of a day as its window says it */
function ppMinutes(object $ctx, string $token, string $planId, int $number = 1): ?int
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$planId}/days/{$number}")->assertOk()->json('data.window.day.minutes_estimate');
}

// Canon: «цена живёт в конфиге; у плана — снимок plan.pace». CATCHES a plan made without its list, and a window that reads
// the config instead of the plan's own list.
it('gives a new plan the price list of the config, and reckons its days by it', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];

    expect(ppStored($id))->toEqual(config('plan.pace'))
        ->and(ppStored($id))->toEqual(DayPace::DEFAULTS);

    // The plan's own list, twice the config's: its day is reckoned by it, not by the config.
    $before = ppMinutes($this, $token, $id);
    DB::table('plans')->where('id', $id)->update(['pace' => json_encode(array_map(static fn (int $s): int => 2 * $s, DayPace::DEFAULTS))]);
    $doubled = ppMinutes($this, $token, $id);

    expect($before)->toBeGreaterThan(0)
        ->and($doubled)->toBeGreaterThan($before);
});

// Canon: «команда plan:repace --all (идемпотентная) пересчитывает снимки и minutes_estimate незакрытых дней — входит в
// выкат». CATCHES a command that writes a plan already on the list, a dry run that writes, a second run that writes again,
// and minutes of the open days not named.
it('gives every plan the config\'s list once — a dry run and a second run write nothing — and names the minutes of open days', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $old = array_map(static fn (int $s): int => 3 * $s, DayPace::DEFAULTS);
    DB::table('plans')->where('id', $id)->update(['pace' => json_encode($old)]);
    $made = planCreate($this, $token, ['days_total' => 2])['id'];
    $before = ppMinutes($this, $token, $id);

    expect(Artisan::call('plan:repace', ['--all' => true, '--dry' => true]))->toBe(0);
    $dry = Artisan::output();
    expect(ppStored($id))->toEqual($old)
        ->and($dry)->toContain("[--dry] план {$id} · день 1: {$before} → ")
        ->and($dry)->toContain("план {$made} — прейскурант уже нынешний, не тронут")
        ->and($dry)->toContain('записано: 0');

    Artisan::call('plan:repace', ['--all' => true]);
    $after = ppMinutes($this, $token, $id);
    expect(ppStored($id))->toEqual(DayPace::DEFAULTS)
        ->and(Artisan::output())->toContain("план {$id} · день 1: {$before} → {$after} мин")->toContain('записано: 1')
        ->and($after)->toBeLessThan($before);

    Artisan::call('plan:repace', ['--all' => true]);
    expect(Artisan::output())->toContain('записано: 0')
        ->and(Artisan::call('plan:repace'))->toBe(1);
});

// Canon: a plan made before the prices were measured keeps the list it WAS made with until `plan:repace` — nothing about
// it moves at the migration, and the deploy's repace says what moved. CATCHES a migration that leaves the old plans with
// no list (they would read the new prices silently, and repace would show «31 → 31»), and one that gives them the new.
it('gives the plans made before the measured prices the list they were made with', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $migration = require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_23_100000_add_pace_to_plans.php');
    $migration->down();
    $migration->up();

    expect(ppStored($id))->toMatchArray(['phrase_other_slot' => 25, 'speak_echo' => 25, 'dialogue_answer' => 30, 'listen_dialogue' => 110])
        ->and(ppStored($id))->toHaveCount(count(DayPace::DEFAULTS))
        ->and(ppStored($id))->not->toEqual(DayPace::DEFAULTS);
});
