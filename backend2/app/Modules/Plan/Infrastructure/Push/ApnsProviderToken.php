<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Push;

use Illuminate\Contracts\Cache\Repository as Cache;
use RuntimeException;

/**
 * The APNs provider token: a JWT `{alg: ES256, kid}` / `{iss: team, iat}` signed with the .p8 key.
 *
 * Apple accepts a token for an hour and refuses one refreshed more often than every 20 minutes
 * (`TooManyProviderTokenUpdates`), so it is minted once and cached for 50 minutes. `openssl_sign`
 * returns an ASN.1 DER signature; a JWS wants the raw 64-byte R‖S — {@see derToJose()} converts.
 */
final readonly class ApnsProviderToken
{
    public const TTL_SECONDS = 50 * 60;

    public function __construct(
        private Cache $cache,
        private string $keyP8,
        private string $keyId,
        private string $teamId,
    ) {}

    public function current(): string
    {
        /** @var string */
        return $this->cache->remember($this->cacheKey(), self::TTL_SECONDS, fn (): string => $this->mint(time()));
    }

    /** APNs said `ExpiredProviderToken` / `InvalidProviderToken`: the next letter mints a new one. */
    public function forget(): void
    {
        $this->cache->forget($this->cacheKey());
    }

    public function mint(int $issuedAt): string
    {
        $key = openssl_pkey_get_private($this->pem());
        if ($key === false) {
            throw new RuntimeException('APNS_KEY_P8 is not a readable EC private key.');
        }

        $segments = self::base64Url((string) json_encode(['alg' => 'ES256', 'kid' => $this->keyId]))
            .'.'.self::base64Url((string) json_encode(['iss' => $this->teamId, 'iat' => $issuedAt]));
        if (! openssl_sign($segments, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the APNs provider token.');
        }

        return $segments.'.'.self::base64Url(self::derToJose((string) $der));
    }

    /** `APNS_KEY_P8` is either the key's contents or a path to the .p8 file. */
    private function pem(): string
    {
        $value = trim($this->keyP8);
        if (str_starts_with($value, '-----BEGIN')) {
            return str_replace('\n', "\n", $value);
        }
        if (is_file($value)) {
            return (string) file_get_contents($value);
        }

        throw new RuntimeException('APNS_KEY_P8 is neither a PEM key nor a readable file.');
    }

    private function cacheKey(): string
    {
        return 'plan.apns.provider_token.'.$this->teamId.'.'.$this->keyId;
    }

    /** DER `SEQUENCE { INTEGER r, INTEGER s }` → 32-byte r ‖ 32-byte s. */
    public static function derToJose(string $der): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$offset]) !== 0x02) {
                throw new RuntimeException('Unexpected ECDSA signature encoding.');
            }
            $length = ord($der[$offset + 1]);
            $int = substr($der, $offset + 2, $length);
            $offset += 2 + $length;
            $parts[] = str_pad(ltrim($int, "\x00"), 32, "\x00", STR_PAD_LEFT);
        }

        return $parts[0].$parts[1];
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
