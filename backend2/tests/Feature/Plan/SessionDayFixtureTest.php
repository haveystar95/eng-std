<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * THE DEALT DAY THE CLIENT IS BUILT FROM, HELD BYTE FOR BYTE (наряд SESSION-1a, разд. 6):
 * `docs/fixtures/day-doctor.json` (intermediate) and `docs/fixtures/day-doctor-beginner.json` (beginner) are the reply
 * of `GET /api/v1/plans/{id}/days/1` for day 1 of a plan built by the default `FakePlanModel` — the clean doctor
 * lesson, opened, voiced by the fake synthesizer — with every card of the registry inside `stages[].cards`. They are
 * the input of the client's order (SESSION-1b), so a change of any key, value or order of the day fails here first.
 *
 * What changes from run to run is normalised, and nothing else: every ULID becomes `ulid-NNNN` by its first appearance
 * (a card's id and the address of its sound stay tied together), every ISO date or time a fixed token. The day itself
 * is seeded by its scene's id (every rotation and shuffle, the served checks), so the plan's scenes are given fixed
 * ids before the day is opened — the same plan, only the name of each scene pinned.
 *
 * `UPDATE_SESSION_FIXTURES=1` writes the files; without it the reply must be exactly what is written.
 */

/** The real voice pipe over the fake vendor, switched on before the plan is built — every line of the day voiced. */
function s1fxVoice(): void
{
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer(FakeSpeechSynthesizer::OK));
}

/**
 * Gives every scene of the plan a fixed id, its order in the plan: a copy of the row under the new id, its days, terms
 * and sounds moved over, the old row gone — a primary key others refer to is not updated in place.
 */
function s1fxPinScenes(string $planId): void
{
    $columns = array_values(array_filter(Schema::getColumnListing('plan_scenes'), static fn (string $c): bool => $c !== 'id'));
    $quoted = implode(', ', array_map(static fn (string $c): string => '"'.$c.'"', $columns));
    $select = implode(', ', array_map(static fn (string $c): string => $c === 'order' ? '"order" - 1000' : '"'.$c.'"', $columns));

    foreach (DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get(['id', 'order']) as $scene) {
        $old = (string) $scene->id;
        $new = sprintf('01J8SESS1XTVRESCENE%07d', (int) $scene->order);
        DB::update('UPDATE plan_scenes SET "order" = "order" + 1000 WHERE id = ?', [$old]);
        DB::insert("INSERT INTO plan_scenes (id, {$quoted}) SELECT ?, {$select} FROM plan_scenes WHERE id = ?", [$new, $old]);
        foreach (['plan_days', 'plan_terms', 'plan_line_audios'] as $table) {
            DB::table($table)->where('scene_id', $old)->update(['scene_id' => $new]);
        }
        DB::table('plan_scenes')->where('id', $old)->delete();
    }
}

/**
 * The reply as the file holds it: pretty, unescaped, one trailing newline; ULIDs by first appearance, dates and times
 * as tokens.
 *
 * @param  array<string, mixed>  $reply
 */
function s1fxNormalise(array $reply): string
{
    // A share stays a share in the file, as on the wire: `coverage_min` 1.0, not 1.
    $json = json_encode($reply, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $tokens = [];
    $json = (string) preg_replace_callback('/(?<![0-9A-Za-z])[0-9A-HJKMNP-TV-Z]{26}(?![0-9A-Za-z])/', static function (array $m) use (&$tokens): string {
        return $tokens[$m[0]] ??= sprintf('ulid-%04d', count($tokens) + 1);
    }, $json);
    $json = (string) preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?/', '<datetime>', $json);
    $json = (string) preg_replace('/(?<!\d)\d{4}-\d{2}-\d{2}(?!\d)/', '<date>', $json);

    return $json."\n";
}

/**
 * Every sound object of a value, wherever it stands.
 *
 * @return list<array<string, mixed>>
 */
function s1fxSounds(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }
    $keys = array_keys($value);
    sort($keys);
    if ($keys === ['duration_ms', 'ref', 'url', 'voice']) {
        return [$value];
    }

    return array_merge([], ...array_map(s1fxSounds(...), array_values($value)));
}

// Canon (разд. 6): «фикстура полного дня для клиента … детерминированные, тест держит их байт-в-байт». Catches any
// change of the day's wire — a key, a value, an order — and a day that is not the same twice.
it('holds day 1 of the clean doctor lesson byte for byte, every card of the registry in its stage', function (string $level, string $file, int $phrases) {
    s1fxVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2, 'level' => $level])['id'];
    s1fxPinScenes($id);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    $cards = array_merge(...array_column($room['stages'], 'cards'));
    $sounds = s1fxSounds($cards);

    // What the file is for: the day of the registry, dealt and voiced — checked here, not only by eye.
    // Диалог is 13 since наряд BACK-TAILS-1 §1.5: an ask deals one card, not two. «Фразы» differs by level since the
    // ceiling decision of 20.09: «Скажи целиком» is a series of rounds, the beginner's is two rounds and leaves the
    // stage room for two THIRD recognitions under its 690 s, the intermediate's three fill it and give up round three.
    // Eight rows since наряд BACK-TAILS-2 §3: «Вспомнить» is the rehearsal's and «Повторение» a review day's (both absent
    // here), and the talk has no cards at all.
    expect(array_map(static fn (array $s): int => count($s['cards']), $room['stages']))->toBe([24, $phrases, 13, 9, 8, 0, 0, 0])
        ->and(array_diff(array_unique(array_column($cards, 'kind')), array_map(static fn (CardKind $k): string => $k->value, CardKind::dealt())))->toBe([])
        ->and($sounds)->not->toBeEmpty()
        ->and(array_filter($sounds, static fn (array $s): bool => ! is_string($s['url']) || ! is_int($s['duration_ms'])))->toBe([]);

    $path = base_path("docs/fixtures/{$file}.json");
    $json = s1fxNormalise($room);
    if (getenv('UPDATE_SESSION_FIXTURES') === '1') {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $json);
    }

    expect(is_file($path))->toBeTrue("{$file}.json is missing — run with UPDATE_SESSION_FIXTURES=1 once")
        ->and($json === (string) file_get_contents($path))->toBeTrue("the day differs from docs/fixtures/{$file}.json");
})->with([
    'intermediate' => ['intermediate', 'day-doctor', 24],
    'beginner' => ['beginner', 'day-doctor-beginner', 26],
]);
