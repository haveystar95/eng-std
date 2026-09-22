<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * THE ECHO CARDS DEALT BEFORE CONV-2 GET THEIR OWN LINE (наряд BACK-TAILS-2, дополнение по отчёту клиента 1c). Until CONV-2
 * «Повтори через паузу» stood on the partner's line, under `partner_line`, with no `own_line`; build 1.0.0 (19) draws the
 * echo from `own_line` alone, and ten cards of the live base were dealt the old way. A data migration gives each the
 * learner's line of its exchange, from the source a new deal takes it from, and touches nothing else.
 */

/** @return array{id: string, payload: array<string, mixed>} the echo card of day 1 of a new plan, as a deal writes it today */
function oeEchoCard(object $ctx): array
{
    app('auth')->forgetGuards(); // the request guard remembers the last bearer within one test — each card is another learner's
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($ctx, $token, $id, 1);
    $dayId = DB::table('plan_days')->where('plan_id', $id)->where('number', 1)->value('id');
    $row = DB::table('day_cards')->where('day_id', $dayId)->where('kind', 'speak_echo')->first(['id', 'payload']);

    return ['id' => (string) $row->id, 'payload' => json_decode((string) $row->payload, true)];
}

/** @return array<string, mixed> */
function oePayload(string $cardId): array
{
    return json_decode((string) DB::table('day_cards')->where('id', $cardId)->value('payload'), true);
}

/** @param array<string, mixed> $payload */
function oeWrite(string $cardId, array $payload): void
{
    DB::table('day_cards')->where('id', $cardId)->update(['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
}

/**
 * The card as the deal before CONV-2 wrote it: the PARTNER's line of the exchange under `partner_line`, its text
 * expected, `coverage_min` of the rule before FIX-2 — and no line of the learner's.
 *
 * @param  array<string, mixed>  $dealt
 * @return array<string, mixed>
 */
function oeOldForm(array $dealt, int $step): array
{
    $ref = 'x'.$step;

    return [
        'scene_id' => $dealt['scene_id'],
        'exchange' => [...$dealt['exchange'], 'step' => $step],
        'partner_line' => [
            'ref' => $ref, 'text_target' => 'Please bring a towel.', 'text_native' => 'Возьмите полотенце.',
            'audio' => ['ref' => $ref, 'voice' => 'partner', 'url' => null, 'duration_ms' => null],
        ],
        'expected_text' => 'Please bring a towel.',
        'coverage_min' => 0.8,
        'pause_ms' => 3000,
    ];
}

// Canon (дополнение, п. 1): «дописать own_line: только speak_echo без own_line — реплика ученика того же обмена, тем же
// источником, что у новой раздачи; ничего другого в payload не трогать; миграция обратимая». The card dealt today is the
// yardstick: stripped to the old form, the migration must give it back the very `own_line` the deal gave it. CATCHES an
// own line built from anything but the deal's source (the partner's line, the card's `expected_text`), a key of the old
// payload changed or dropped, a line invented for an exchange the lesson does not have, an echo of CONV-2 or of today
// touched on the way up or down, a second run that writes again, and a way back that leaves the added line.
it('gives an echo dealt before CONV-2 its own line as a new deal builds it, touches nothing else, and takes it back', function () {
    $old = oeEchoCard($this);
    $conv2 = oeEchoCard($this);
    $today = oeEchoCard($this);
    $lost = oeEchoCard($this);

    $dealt = $old['payload']['own_line'];
    $oldForm = oeOldForm($old['payload'], (int) $old['payload']['exchange']['step']);
    oeWrite($old['id'], $oldForm);
    // CONV-2's echo: the learner's line under both keys.
    $conv2Form = [...$conv2['payload'], 'partner_line' => $conv2['payload']['own_line']];
    oeWrite($conv2['id'], $conv2Form);
    // An old card whose exchange the lesson does not have: there is no line to give it.
    $lostForm = oeOldForm($lost['payload'], 99);
    oeWrite($lost['id'], $lostForm);

    expect($dealt['ref'])->toBe('x'.$old['payload']['exchange']['step'].'b')
        ->and(array_key_exists('partner_line', $today['payload']))->toBeFalse();

    $migration = require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_22_110000_add_own_line_to_old_echo_cards.php');
    $migration->up();

    expect(oePayload($old['id']))->toEqual([...$oldForm, 'own_line' => $dealt])
        ->and(oePayload($old['id'])['own_line'])->toEqual($dealt)
        ->and(oePayload($conv2['id']))->toEqual($conv2Form)
        ->and(oePayload($today['id']))->toEqual($today['payload'])
        ->and(oePayload($lost['id']))->toEqual($lostForm);

    // Once is enough: a card that has its line is not read again.
    $migration->up();
    expect(oePayload($old['id']))->toEqual([...$oldForm, 'own_line' => $dealt]);

    $migration->down();
    expect(oePayload($old['id']))->toEqual($oldForm)
        ->and(oePayload($conv2['id']))->toEqual($conv2Form)
        ->and(oePayload($today['id']))->toEqual($today['payload'])
        ->and(oePayload($lost['id']))->toEqual($lostForm);
});
