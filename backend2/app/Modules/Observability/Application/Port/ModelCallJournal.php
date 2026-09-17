<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Observability\Application\Dto\ModelCallStart;
use App\Modules\Observability\Application\Dto\ModelCallUsage;
use DateTimeImmutable;

/**
 * THE JOURNAL OF MODEL CALLS — what every call to a text model spent (наряд GEN-3).
 *
 * A row is written BEFORE the call (`started`) and finished after it: `completed` with the usage, the cost and the cached
 * tokens; `failed` when the vendor answered with an error status (no usage, nothing billed); `lost` when no answer came —
 * our client gave up (a timeout, a dropped connection) or the process ended mid-call ({@see sweepLost()}). A lost call is
 * the one the vendor may still have finished and billed: it stays in the journal so the money it took is not a mystery.
 *
 * Model calls are made outside database transactions (a lock is never held over a vendor call), so a row is committed when
 * it is written. Implementations never throw into the call they record: a journal that cannot write logs it and the call
 * goes on — `started()` then returns null, and the later marks for a null id do nothing.
 */
interface ModelCallJournal
{
    public function started(ModelCallStart $call): ?string;

    public function completed(?string $id, ModelCallUsage $usage, int $httpStatus, int $latencyMs): void;

    public function failed(?string $id, int $httpStatus, string $error, int $latencyMs): void;

    public function lost(?string $id, string $error, int $latencyMs): void;

    /**
     * Mark `lost` every call still `started` whose caller stopped waiting more than `$graceSeconds` ago — its process ended
     * before the answer was recorded (a worker killed at its job's timeout). Returns how many were marked.
     */
    public function sweepLost(DateTimeImmutable $now, int $graceSeconds): int;
}
