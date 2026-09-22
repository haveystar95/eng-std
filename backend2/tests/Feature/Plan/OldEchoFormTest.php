<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\SpeechMatch;
use App\Modules\Shared\Domain\ValueObject\SpeechMode;
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
 * THE ECHO CARDS DEALT BEFORE CONV-2 TAKE TODAY'S FORM (наряд BACK-TAILS-2, дополнение по отчёту клиента 1c). Until CONV-2
 * «Повтори через паузу» stood on the partner's line — `partner_line`, its text expected, no `own_line`; build 1.0.0 (19)
 * draws the echo from `own_line` alone, and ten cards of the live base were dealt the old way. A data migration brings
 * each to the form a new deal gives its exchange — `own_line`, `expected_text`, `speech_mode`, no `coverage_min` — keeps
 * `partner_line` for the way back, and touches nothing else.
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
 * The card as a deal before CONV-2 wrote it: the PARTNER's line of the exchange under `partner_line`, its text expected,
 * no line of the learner's — and the rule of speech of its time: `coverage_min` 0.7 before FIX-2, `speech_mode` after.
 *
 * @param  array<string, mixed>  $dealt  the card as it is dealt today — whose scene and exchange it keeps
 * @param  array<string, mixed>  $rule
 * @return array<string, mixed>
 */
function oeOldForm(array $dealt, int $step, array $rule): array
{
    $ref = 'x'.$step;

    return [
        'scene_id' => $dealt['scene_id'],
        'exchange' => [...$dealt['exchange'], 'ref' => $ref, 'step' => $step],
        'partner_line' => [
            'ref' => $ref, 'text_target' => 'Please bring a towel.', 'text_native' => 'Возьмите полотенце.',
            'audio' => ['ref' => $ref, 'voice' => 'partner', 'url' => null, 'duration_ms' => null],
        ],
        'expected_text' => 'Please bring a towel.',
        'pause_ms' => 3000,
        ...$rule,
    ];
}

/** The card's day as the deal before FIX-2 dealt it: its other cards carry `coverage_min`, not `speech_mode`. */
function oeDealtBeforeFix2(string $cardId): void
{
    $dayId = DB::table('day_cards')->where('id', $cardId)->value('day_id');
    foreach (DB::table('day_cards')->where('day_id', $dayId)->where('id', '<>', $cardId)->get(['id', 'payload']) as $row) {
        $payload = json_decode((string) $row->payload, true);
        if (array_key_exists('speech_mode', $payload)) {
            unset($payload['speech_mode']);
            oeWrite((string) $row->id, [...$payload, 'coverage_min' => 0.7]);
        }
    }
}

// Canon (дополнение по отчёту клиента 1c, решение Дена по §5 п. 11): «та же миграция приводит 10 старых эх к нынешней форме
// целиком: own_line — реплика ученика того же обмена тем же источником, что у новой раздачи; expected_text =
// own_line.text_target; speech_mode — как даёт новая раздача эха; coverage_min снять; partner_line оставить как есть.
// Откат: expected_text обратно из partner_line, coverage_min — прежней константой раздачи, own_line и speech_mode снять;
// только у карточек, где partner_line — реплика собеседника». The card dealt today is the yardstick: stripped to an old
// form, it must come back equal to the fresh deal of its exchange but for `partner_line`, and be passed by its own line.
// CATCHES a line built from anything but the deal's source, an old `expected_text` or `coverage_min` left on the card, a
// key of the old payload lost, a line invented for an exchange the lesson does not have, an echo of CONV-2 or of today
// touched on the way up or down, a second run that writes again, a way back that is not the old form byte for byte —
// and a way back that gives `coverage_min` to an echo dealt after FIX-2, which never had it (the owner's gym day 1).
it('brings an echo dealt before CONV-2 to the form of a fresh deal, passed by its own line, and takes it back', function () {
    $old = oeEchoCard($this);
    $fix2 = oeEchoCard($this);
    $conv2 = oeEchoCard($this);
    $today = oeEchoCard($this);
    $lost = oeEchoCard($this);

    $step = (int) $old['payload']['exchange']['step'];
    $oldForm = oeOldForm($old['payload'], $step, ['coverage_min' => 0.7]);
    oeWrite($old['id'], $oldForm);
    oeDealtBeforeFix2($old['id']);
    // Dealt between FIX-2 and CONV-2, like the owner's gym day 1: already `speech_mode`, and no `coverage_min` ever.
    $fix2Form = oeOldForm($fix2['payload'], (int) $fix2['payload']['exchange']['step'], ['speech_mode' => 'repeat']);
    oeWrite($fix2['id'], $fix2Form);
    // CONV-2's echo: the learner's line under both keys.
    $conv2Form = [...$conv2['payload'], 'partner_line' => $conv2['payload']['own_line']];
    oeWrite($conv2['id'], $conv2Form);
    // An old card whose exchange the lesson does not have: there is no line to give it.
    $lostForm = oeOldForm($lost['payload'], 99, ['coverage_min' => 0.7]);
    oeWrite($lost['id'], $lostForm);

    expect($old['payload']['own_line']['ref'])->toBe("x{$step}b")
        ->and(array_key_exists('partner_line', $today['payload']))->toBeFalse();

    $migration = require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_22_110000_bring_old_echo_cards_to_todays_form.php');
    $migration->up();

    // The fresh deal of the same exchange, `partner_line` aside — every other key and value.
    expect(oePayload($old['id']))->toEqual([...$old['payload'], 'partner_line' => $oldForm['partner_line']])
        ->and(oePayload($fix2['id']))->toEqual([...$fix2['payload'], 'partner_line' => $fix2Form['partner_line']])
        ->and(oePayload($conv2['id']))->toEqual($conv2Form)
        ->and(oePayload($today['id']))->toEqual($today['payload'])
        ->and(oePayload($lost['id']))->toEqual($lostForm);

    // Passed by coverage of its OWN line, by the one rule of speech — and no longer by the partner's.
    $migrated = oePayload($old['id']);
    $said = static fn (string $heard): bool => (new SpeechMatch)->said(
        $heard, $migrated['expected_text'], SpeechMode::from($migrated['speech_mode']), lessonPacks()->for('en')->speech(),
    );
    expect($said($migrated['own_line']['text_target']))->toBeTrue()
        ->and($said('Please bring a towel.'))->toBeFalse();

    // Once is enough: a card in today's form is not read again.
    $migration->up();
    expect(oePayload($old['id']))->toEqual([...$old['payload'], 'partner_line' => $oldForm['partner_line']]);

    $migration->down();
    expect(oePayload($old['id']))->toEqual($oldForm)
        ->and(oePayload($fix2['id']))->toEqual($fix2Form)
        ->and(oePayload($conv2['id']))->toEqual($conv2Form)
        ->and(oePayload($today['id']))->toEqual($today['payload'])
        ->and(oePayload($lost['id']))->toEqual($lostForm);
});
