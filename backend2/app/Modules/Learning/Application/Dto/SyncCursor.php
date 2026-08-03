<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;
use JsonException;

/**
 * Opaque pagination cursor for GET /sync. Carries the FROZEN upper bound (`server_time` captured
 * on the first page) so later pages see the same change-set even as new writes land, plus the
 * offset into the deterministically-ordered change stream. The client passes it back verbatim
 * until has_more is false, then adopts server_time as the next `since`.
 */
final readonly class SyncCursor
{
    public function __construct(
        public DateTimeImmutable $upper,
        public int $offset,
    ) {}

    public function encode(): string
    {
        return base64_encode(json_encode(
            ['u' => $this->upper->format(DATE_ATOM), 'o' => $this->offset],
            JSON_THROW_ON_ERROR,
        ));
    }

    /** Returns null for a malformed cursor (client should restart the sync from its stored `since`). */
    public static function decode(string $raw): ?self
    {
        $json = base64_decode($raw, true);
        if ($json === false) {
            return null;
        }
        try {
            /** @var mixed $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (! is_array($data) || ! isset($data['u'], $data['o'])) {
            return null;
        }
        try {
            $upper = new DateTimeImmutable((string) $data['u']);
        } catch (\Exception) {
            return null;
        }

        return new self($upper, (int) $data['o']);
    }
}
