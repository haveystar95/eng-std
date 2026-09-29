<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * «GET ДНЯ СОВМЕСТИМ С КЛИЕНТОМ» (наряд GEN-2a): the day read and the day's cards of a `lesson_day.v4.5` lesson carry
 * every key the phone reads today, with a value of the type it reads — the new fields (frame, slot, fillers,
 * listening, used_in, kind, phrase_ref, filler) are additive.
 *
 * What the phone reads is its own fixtures, captured from the server and parsed by its golden tests:
 * `mobile/test/fixtures/plan/room_window_in_progress.json` (client commit `4816a034`), copied into
 * `tests/Fixtures/plan-client/` — the backend container does not see the client's tree. The copy changes one value,
 * never a key: `scene.prompt_version` names the current prompt (only kinds are compared). Catches a key renamed or
 * dropped by the new lesson (a phrase without `pronunciation`, a learner line without `state`), and a key whose value
 * changed its kind.
 *
 * The cards are no longer held against the phone's copy (наряд SESSION-1a): the thirteen kinds that fixture captured
 * are gone without compatibility (DECISIONS п. 325), and the day's cards of the registry are held byte for byte by
 * `docs/fixtures/day-doctor*.json` ({@see SessionDayFixtureTest}) — the input of the client's next order. `stages[]`
 * gains `cards` additively, which the room's comparison tolerates.
 */

/**
 * Every key path of a JSON value with the kinds of value found at it: an object's key is `.key`, a list's items
 * `[]`; cards are grouped by their kind (`cards{word_intro}`), since each kind has its own payload.
 *
 * @param  array<string, array<string, true>>  $out
 */
function dccShape(mixed $value, string $path, array &$out): void
{
    $kind = match (true) {
        $value === null => 'null',
        is_bool($value) => 'bool',
        is_int($value), is_float($value) => 'number',
        is_string($value) => 'string',
        is_array($value) && array_is_list($value) => 'list',
        default => 'object',
    };
    $out[$path][$kind] = true;
    if (! is_array($value)) {
        return;
    }
    if ($path === '.cards') {
        foreach ($value as $card) {
            dccShape($card, ".cards{{$card['kind']}}", $out);
        }

        return;
    }
    foreach ($value as $key => $item) {
        dccShape($item, array_is_list($value) ? "{$path}[]" : "{$path}.{$key}", $out);
    }
}

/**
 * The paths the client reads that the server does not give, or gives as another kind of value. A value the server
 * gives as null where the client has one (no photo, no voice yet) is the server's to leave empty — its inner keys
 * are not asked for.
 *
 * @return list<string>
 */
function dccMissing(array $client, array $server): array
{
    $clientShape = [];
    $serverShape = [];
    dccShape($client, '', $clientShape);
    dccShape($server, '', $serverShape);
    $empty = [];
    foreach ($serverShape as $path => $kinds) {
        if (array_keys($kinds) === ['null']) {
            $empty[] = $path;
        }
    }

    $missing = [];
    foreach ($clientShape as $path => $kinds) {
        foreach ($empty as $prefix) {
            if (str_starts_with($path, $prefix.'.') || str_starts_with($path, $prefix.'[')) {
                continue 2;
            }
        }
        if (preg_match('/^\.cards\{([a-z_]+)\}/', $path, $m) === 1 && ! isset($serverShape[".cards{{$m[1]}}"])) {
            continue; // a card kind this day does not deal
        }
        if (! isset($serverShape[$path])) {
            $missing[] = "{$path} (absent)";

            continue;
        }
        $clientKinds = array_diff(array_keys($kinds), ['null']);
        $serverKinds = array_diff(array_keys($serverShape[$path]), ['null']);
        if ($clientKinds !== [] && $serverKinds !== [] && array_intersect($clientKinds, $serverKinds) === []) {
            $missing[] = "{$path} (".implode('|', $serverKinds).' instead of '.implode('|', $clientKinds).')';
        }
    }

    return $missing;
}

/** @return array<string, mixed> */
function dccFixture(string $name): array
{
    return json_decode((string) file_get_contents(base_path("tests/Fixtures/plan-client/{$name}.json")), true, 512, JSON_THROW_ON_ERROR);
}

it('gives the day read every key the phone reads, of the kind it reads — a day being walked', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $queue = array_values(array_filter(planOpenDay($this, $token, $id, 1)['cards'], static fn (array $c): bool => $c['stage'] === 'words'));
    foreach ($queue as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], 'passed');
    }

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');

    expect(dccMissing(dccFixture('room_window_in_progress'), $room))->toBe([])
        // The additive fields are there for whoever reads them next.
        ->and($room['window']['program']['phrases']['items'][0]['frame']['slot']['fillers'][0])->toHaveKeys(['target', 'native', 'pronunciation', 'in_dialogue'])
        ->and($room['window']['program']['words']['items'][0]['used_in'])->toBe(['p1'])
        ->and($room['window']['program']['dialogue']['items'][0]['kind'])->toBe('answer')
        ->and($room['window']['program']['dialogue']['items'][0]['learner'])->toHaveKeys(['phrase_ref', 'filler'])
        ->and($room['window']['listening'])->toHaveCount(3);
});

// The comparison itself must catch a key that went away — or it proves nothing.
it('names a key the phone reads that the server no longer gives', function () {
    $client = ['window' => ['program' => ['phrases' => ['items' => [['text' => 'x', 'pronunciation' => 'y']]]]]];
    $server = ['window' => ['program' => ['phrases' => ['items' => [['text' => 'x']]]]]];

    expect(dccMissing($client, $server))->toBe(['.window.program.phrases.items[].pronunciation (absent)'])
        ->and(dccMissing(['a' => 1], ['a' => 'one']))->toBe(['.a (string instead of number)'])
        ->and(dccMissing(['image' => ['url' => 'u']], ['image' => null]))->toBe([]);
});
