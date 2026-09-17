<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Dto\ModelCallStart;
use App\Modules\Observability\Application\Dto\ModelCallUsage;
use App\Modules\Observability\Application\Port\ModelCallJournal;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The journal in `model_calls`. Every write runs in its own transaction — a SAVEPOINT when a caller has one open (see
 * {@see EloquentApiLogWriter}: a failed insert must not poison the caller's transaction) — and never throws: a journal that
 * cannot write says so in the log, and the call it was recording goes on.
 */
final class EloquentModelCallJournal implements ModelCallJournal
{
    private const MAX_ERROR = 2000;

    public function started(ModelCallStart $call): ?string
    {
        $id = Ulid::generate();

        return $this->write('started', function () use ($id, $call): void {
            ModelCallModel::query()->create([
                'id' => $id,
                'status' => 'started',
                'provider' => $call->provider,
                'model' => mb_substr($call->model, 0, 100),
                'purpose' => $call->purpose,
                'estimated_tokens_in' => $call->estimatedTokensIn,
                'timeout_seconds' => $call->timeoutSeconds,
                'started_at' => now(),
            ]);
        }) ? $id : null;
    }

    public function completed(?string $id, ModelCallUsage $usage, int $httpStatus, int $latencyMs): void
    {
        $this->finish($id, [
            'status' => 'completed',
            'answered_model' => mb_substr($usage->answeredModel, 0, 100),
            'tokens_in' => $usage->tokensIn,
            'cached_tokens' => $usage->cachedTokensIn,
            'tokens_out' => $usage->tokensOut,
            'cost_usd' => $usage->costUsd,
            'http_status' => $httpStatus,
            'latency_ms' => $latencyMs,
        ]);
    }

    public function failed(?string $id, int $httpStatus, string $error, int $latencyMs): void
    {
        $this->finish($id, ['status' => 'failed', 'http_status' => $httpStatus, 'error' => mb_substr($error, 0, self::MAX_ERROR), 'latency_ms' => $latencyMs]);
    }

    public function lost(?string $id, string $error, int $latencyMs): void
    {
        $this->finish($id, ['status' => 'lost', 'error' => mb_substr($error, 0, self::MAX_ERROR), 'latency_ms' => $latencyMs]);
    }

    public function sweepLost(DateTimeImmutable $now, int $graceSeconds): int
    {
        $marked = 0;
        $this->write('sweep', function () use ($now, $graceSeconds, &$marked): void {
            $marked = ModelCallModel::query()
                ->where('status', 'started')
                ->whereRaw("started_at + make_interval(secs => timeout_seconds + ?) < ?", [$graceSeconds, $now->format(DATE_ATOM)])
                ->update([
                    'status' => 'lost',
                    'error' => 'no answer recorded: the process ended while waiting for the vendor',
                    'finished_at' => $now,
                ]);
        });

        return $marked;
    }

    /** @param array<string, mixed> $fields */
    private function finish(?string $id, array $fields): void
    {
        if ($id === null) {
            return;
        }
        $this->write($fields['status'], static function () use ($id, $fields): void {
            ModelCallModel::query()->whereKey($id)->where('status', 'started')->update([...$fields, 'finished_at' => now()]);
        });
    }

    private function write(string $what, callable $write): bool
    {
        try {
            DB::transaction($write(...));

            return true;
        } catch (Throwable $e) {
            Log::warning('model call journal not written', ['write' => $what, 'error' => mb_substr($e->getMessage(), 0, 300)]);

            return false;
        }
    }
}
