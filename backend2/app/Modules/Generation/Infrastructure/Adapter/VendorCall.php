<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Dto\ModelCallStart;
use App\Modules\Observability\Application\Dto\ModelCallUsage;
use App\Modules\Observability\Application\Port\ModelCallJournal;
use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * ONE CALL TO A TEXT MODEL, AS EVERY ADAPTER OF THIS MODULE MAKES IT (наряд GEN-3) — the policy the adapters share, in one
 * place:
 *
 *  - **the journal** ({@see ModelCallJournal}): a row before the call — vendor, model, what it is for, the input tokens
 *    estimated from the body, how long we wait — and after it `completed` with the usage and the cost, `failed` with the
 *    vendor's error status, or `lost` when no answer came at all;
 *  - **the timeouts**: {@see CONNECT_TIMEOUT} to reach the vendor — short, an unreachable host is known in seconds — and
 *    the caller's own wait for the answer (180 s for the plan's calls): a strong model writing a lesson answers in 30–51 s,
 *    and a client that dropped it at 60 s paid for an answer it never read («вызов оборван на 60 с, модель досчитала,
 *    деньги списаны, ответа нет»);
 *  - **what is retried**: only a vendor that ANSWERED with a status that clears by itself (a rate window, an overload). A
 *    call that got no answer — our timeout, a dropped connection — is never sent again: the vendor may still be computing
 *    it, and a second send buys it twice. Repeating it is a person's decision.
 */
final readonly class VendorCall
{
    /** Seconds to establish the connection to a vendor. */
    public const CONNECT_TIMEOUT = 10;

    /** The statuses a vendor answers with that can change on their own — worth another attempt after a backoff. */
    public const RETRY_STATUSES = [408, 409, 429, 500, 502, 503, 504];

    public function __construct(private ModelCallJournal $journal) {}

    /**
     * Whether a failed attempt is sent again: a vendor's answer with a status that clears by itself, and nothing else.
     * A 429 is a per-minute window, so the backoff escalates to cross it ({@see backoff()}); a 403 (no credits, wrong key)
     * answers the same forever; a timeout or a dropped connection has no answer and is never repeated.
     */
    public static function retryable(Throwable $e): bool
    {
        return $e instanceof RequestException && in_array($e->response->status(), self::RETRY_STATUSES, true);
    }

    /** Milliseconds before attempt `$attempt + 1`: 4 s, 8 s, 12 s — enough to cross a vendor's per-minute window. */
    public static function backoff(int $attempt): int
    {
        return $attempt * 4000;
    }

    /**
     * Send the request, journalled — the response and the journal's row for it.
     *
     * @param  array<string, mixed>  $body  the request body — its size is the estimate of the input tokens
     * @param  Closure(): Response  $send  the HTTP call itself (with its retries)
     * @param  Closure(Response): ModelCallUsage  $usage  what a successful answer says it spent
     */
    public function send(ProviderId $provider, string $model, ?string $purpose, int $timeoutSeconds, array $body, Closure $send, Closure $usage): SentCall
    {
        $id = $this->journal->started(new ModelCallStart($provider->value, $model, $purpose, ModelCallStart::estimateTokens($body), $timeoutSeconds));
        $startedAt = hrtime(true);

        try {
            $response = $send();
        } catch (Throwable $e) {
            $this->journal->lost($id, $e->getMessage(), self::since($startedAt));

            throw $e;
        }

        $latencyMs = self::since($startedAt);
        if ($response->failed()) {
            $this->journal->failed($id, $response->status(), mb_substr($response->body(), 0, 500), $latencyMs);
        } else {
            $this->journal->completed($id, $usage($response), $response->status(), $latencyMs);
        }

        return new SentCall($response, $id);
    }

    private static function since(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
