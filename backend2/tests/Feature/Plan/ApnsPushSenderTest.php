<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PushMessage;
use App\Modules\Plan\Application\Dto\PushTarget;
use App\Modules\Plan\Application\Port\PushSender;
use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Infrastructure\Push\ApnsPushSender;
use App\Modules\Plan\Infrastructure\Push\DryRunPushSender;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * THE APNs DOOR (PLAN-UI-3) — with a throwaway P-256 key standing in for the .p8 and `Http::fake()`
 * standing in for Apple. Nothing here reaches the network.
 */
uses(RefreshDatabase::class);

/** @return array{pem: string, public: string} */
function apnsTestKey(): array
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);

    return ['pem' => (string) $pem, 'public' => (string) openssl_pkey_get_details($key)['key']];
}

/** JOSE r‖s → DER, to verify the JWT with openssl the way Apple would. */
function apnsJoseToDer(string $raw): string
{
    $int = static function (string $bytes): string {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".chr(strlen($bytes)).$bytes;
    };
    $seq = $int(substr($raw, 0, 32)).$int(substr($raw, 32, 32));

    return "\x30".chr(strlen($seq)).$seq;
}

function apnsB64UrlDecode(string $s): string
{
    return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
}

function apnsConfigure(string $pem): void
{
    config(['services.apns' => [
        'key_p8' => $pem, 'key_id' => 'KEY1234567', 'team_id' => 'TEAM123456',
        'topic' => 'com.denis.engstd', 'env' => 'sandbox',
    ]]);
}

function apnsMessage(string $userId): PushMessage
{
    return new PushMessage($userId, NotificationKind::DayReady, 'День 2 собран', '«Приём у врача» — можно начинать', Ulid::generate(), 2);
}

it('binds the dry-run sender without a key and the APNs sender with one — catches a live door opened by an empty variable', function () {
    config(['services.apns.key_p8' => '']);
    expect(app(PushSender::class))->toBeInstanceOf(DryRunPushSender::class);

    apnsConfigure(apnsTestKey()['pem']);
    expect(app(PushSender::class))->toBeInstanceOf(ApnsPushSender::class);
});

it('signs an ES256 provider token Apple can verify, sends ids and the alert only, and caches the token — catches a JWT with a DER signature', function () {
    $key = apnsTestKey();
    apnsConfigure($key['pem']);
    [$user] = learner();
    Http::fake(['api.sandbox.push.apple.com/*' => Http::response('', 200, ['apns-id' => 'x'])]);

    $sender = app(PushSender::class);
    $first = $sender->send(apnsMessage($user->id), [new PushTarget('ios', 'aa11')]);
    $sender->send(apnsMessage($user->id), [new PushTarget('ios', 'aa11')]);

    expect($first->status)->toBe(DeliveryStatus::Sent);
    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    /** @var Request $request */
    $request = $requests[0][0];
    expect($request->url())->toBe('https://api.sandbox.push.apple.com/3/device/aa11')
        ->and($request->header('apns-topic')[0])->toBe('com.denis.engstd')
        ->and($request->header('apns-push-type')[0])->toBe('alert');

    $jwt = substr($request->header('authorization')[0], strlen('bearer '));
    [$h, $c, $sig] = explode('.', $jwt);
    expect(json_decode(apnsB64UrlDecode($h), true))->toBe(['alg' => 'ES256', 'kid' => 'KEY1234567'])
        ->and(json_decode(apnsB64UrlDecode($c), true)['iss'])->toBe('TEAM123456')
        ->and(strlen(apnsB64UrlDecode($sig)))->toBe(64)
        ->and(openssl_verify("{$h}.{$c}", apnsJoseToDer(apnsB64UrlDecode($sig)), $key['public'], OPENSSL_ALGO_SHA256))->toBe(1)
        // Cached: the second letter carries the same provider token.
        ->and($requests[1][0]->header('authorization')[0])->toBe($request->header('authorization')[0]);

    expect($request->data())->toBe([
        'aps' => ['alert' => ['title' => 'День 2 собран', 'body' => '«Приём у врача» — можно начинать'], 'sound' => 'default'],
        'kind' => 'day_ready',
        'plan_id' => $request->data()['plan_id'],
        'day_number' => 2,
    ]);
});

it('forgets a dead address on 410 and still counts the letter sent to the live one — catches knocking on an uninstalled app forever', function () {
    apnsConfigure(apnsTestKey()['pem']);
    [$user] = learner();
    foreach (['deadbeef', 'a1ive'] as $token) {
        DB::table('device_push_tokens')->insert([
            'id' => Ulid::generate(), 'user_id' => $user->id, 'platform' => 'ios', 'token' => $token,
            'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    Http::fake([
        'api.sandbox.push.apple.com/3/device/deadbeef' => Http::response(['reason' => 'Unregistered', 'timestamp' => 1], 410),
        'api.sandbox.push.apple.com/3/device/a1ive' => Http::response('', 200),
    ]);

    $result = app(PushSender::class)->send(apnsMessage($user->id), [new PushTarget('ios', 'deadbeef'), new PushTarget('ios', 'a1ive')]);

    expect($result->status)->toBe(DeliveryStatus::Sent)
        ->and($result->reason)->toContain('410 Unregistered (address removed)')
        ->and(DB::table('device_push_tokens')->pluck('token')->all())->toBe(['a1ive']);

    // Every address dead → failed; no address at all → no_token and no request.
    // (Http::fake stubs accumulate, so the refused address is a new one.)
    Http::fake(['api.sandbox.push.apple.com/3/device/badf00d' => Http::response(['reason' => 'BadDeviceToken'], 400)]);
    DB::table('device_push_tokens')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'platform' => 'ios', 'token' => 'badf00d',
        'last_seen_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $failed = app(PushSender::class)->send(apnsMessage($user->id), [new PushTarget('ios', 'badf00d')]);
    expect($failed->status)->toBe(DeliveryStatus::Failed)
        ->and($failed->reason)->toBe('400 BadDeviceToken (address removed)')
        ->and(DB::table('device_push_tokens')->pluck('token')->all())->toBe(['a1ive'])
        ->and(app(PushSender::class)->send(apnsMessage($user->id), [])->status)->toBe(DeliveryStatus::NoToken);
});
