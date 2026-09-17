<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Dto;

/**
 * A call to a text model, as the journal records it BEFORE the call is made (наряд GEN-3): which vendor and model, what the
 * spend is for, how many input tokens the request is estimated at, and how long the caller will wait for the answer — past
 * that (and a margin) a call still `started` is a call whose process ended without an answer.
 */
final readonly class ModelCallStart
{
    /** Bytes of the request body per estimated token — a rough, vendor-free guess; the answer's usage is the fact. */
    private const BYTES_PER_TOKEN = 4;

    public function __construct(
        public string $provider,
        public string $model,
        public ?string $purpose,
        public int $estimatedTokensIn,
        public int $timeoutSeconds,
    ) {}

    /**
     * The input tokens a request body is estimated at, before the vendor counts them.
     *
     * @param  array<string, mixed>  $body
     */
    public static function estimateTokens(array $body): int
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return intdiv(strlen($json === false ? '' : $json), self::BYTES_PER_TOKEN);
    }
}
