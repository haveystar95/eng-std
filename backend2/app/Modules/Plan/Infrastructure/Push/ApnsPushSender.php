<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Push;

use App\Modules\Plan\Application\Dto\DeliveryResult;
use App\Modules\Plan\Application\Dto\PushMessage;
use App\Modules\Plan\Application\Dto\PushTarget;
use App\Modules\Plan\Application\Port\LearnerDevices;
use App\Modules\Plan\Application\Port\PushSender;
use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * APNs over HTTP/2 with a provider token (`APNS_KEY_P8`, `APNS_KEY_ID`, `APNS_TEAM_ID`,
 * `APNS_TOPIC`, `APNS_ENV=sandbox|production`). One request per device address; the letter is
 * `sent` when APNs accepted it for at least one, `failed` when it refused every one, `no_token`
 * when the learner has no address at all (no request is made).
 *
 * A dead address — 410 (`Unregistered`, `ExpiredToken`) or 400 `BadDeviceToken` — is forgotten on
 * the spot through Identity, so the next letter does not knock on it again. An expired or
 * invalid provider token drops the cached JWT so the next letter mints a fresh one.
 *
 * The body carries the alert and the ids only (`kind`, `plan_id`, `day_number`): the client opens
 * the plan through the normal API.
 */
final readonly class ApnsPushSender implements PushSender
{
    private const HOSTS = [
        'production' => 'https://api.push.apple.com',
        'sandbox' => 'https://api.sandbox.push.apple.com',
    ];

    // `DeviceTokenNotForTopic` is NOT here: it means APNS_TOPIC is wrong, and deleting every address
    // over a config mistake would leave nothing to send to once the mistake is fixed.
    private const DEAD_TOKEN_REASONS = ['BadDeviceToken', 'Unregistered', 'ExpiredToken'];

    public function __construct(
        private ApnsProviderToken $providerToken,
        private LearnerDevices $devices,
        private string $topic,
        private string $environment,
        private int $timeoutSeconds = 10,
    ) {}

    public function send(PushMessage $message, array $targets): DeliveryResult
    {
        if ($targets === []) {
            return new DeliveryResult(DeliveryStatus::NoToken, 'the learner has no device address');
        }

        try {
            $jwt = $this->providerToken->current();
        } catch (RuntimeException $e) {
            return new DeliveryResult(DeliveryStatus::Failed, 'apns key: '.$e->getMessage());
        }

        $sent = 0;
        $reasons = [];
        foreach ($targets as $target) {
            $outcome = $this->sendOne($message, $target, $jwt);
            if ($outcome === null) {
                $sent++;
            } else {
                $reasons[] = $outcome;
            }
        }

        if ($sent > 0) {
            return new DeliveryResult(DeliveryStatus::Sent, $reasons === [] ? null : "sent to {$sent} of ".count($targets).'; '.implode('; ', $reasons));
        }

        return new DeliveryResult(DeliveryStatus::Failed, implode('; ', $reasons));
    }

    /** @return string|null null when APNs accepted it, else why not */
    private function sendOne(PushMessage $message, PushTarget $target, string $jwt): ?string
    {
        $url = $this->host().'/3/device/'.rawurlencode($target->token);
        $payload = [
            'aps' => [
                'alert' => ['title' => $message->title, 'body' => $message->body],
                'sound' => 'default',
            ],
        ] + $message->ids();

        try {
            $response = Http::withOptions(['version' => 2.0])
                ->timeout($this->timeoutSeconds)
                ->withHeaders([
                    'authorization' => 'bearer '.$jwt,
                    'apns-topic' => $this->topic,
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                ])
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            return 'connection: '.$e->getMessage();
        }

        if ($response->successful()) {
            return null;
        }

        $reason = (string) ($response->json('reason') ?? '');
        $status = $response->status();
        if ($status === 410 || ($status === 400 && in_array($reason, self::DEAD_TOKEN_REASONS, true))) {
            $this->devices->forget($target);

            return "{$status} {$reason} (address removed)";
        }
        if ($status === 403 && in_array($reason, ['ExpiredProviderToken', 'InvalidProviderToken'], true)) {
            $this->providerToken->forget();
        }

        return trim("{$status} {$reason}");
    }

    private function host(): string
    {
        return self::HOSTS[$this->environment] ?? self::HOSTS['sandbox'];
    }
}
