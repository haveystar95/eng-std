<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Observability\Application\Dto\InboundCallRecord;
use App\Modules\Observability\Application\Dto\ModelCallRecord;
use App\Modules\Observability\Application\Dto\SpeechCallRecord;
use App\Modules\Observability\Application\Port\CallLogReader;
use App\Modules\Plan\Application\Dto\Inspection\JournalClientCall;
use App\Modules\Plan\Application\Dto\Inspection\JournalModelCall;
use App\Modules\Plan\Application\Dto\Inspection\JournalSpeechCall;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use DateTimeImmutable;

/** The plan's reading of the call journal, over Observability's own reader (наряд ADM-1). */
final readonly class ObservabilityPlanCallJournal implements PlanCallJournal
{
    public function __construct(private CallLogReader $log) {}

    public function modelCalls(DateTimeImmutable $from, DateTimeImmutable $to, array $purposes): array
    {
        return array_map(static fn (ModelCallRecord $c): JournalModelCall => new JournalModelCall(
            id: $c->id,
            status: $c->status,
            provider: $c->provider,
            model: $c->model,
            answeredModel: $c->answeredModel,
            purpose: $c->purpose,
            estimatedTokensIn: $c->estimatedTokensIn,
            tokensIn: $c->tokensIn,
            cachedTokens: $c->cachedTokens,
            tokensOut: $c->tokensOut,
            costUsd: $c->costUsd,
            httpStatus: $c->httpStatus,
            error: $c->error,
            latencyMs: $c->latencyMs,
            startedAt: $c->startedAt,
            finishedAt: $c->finishedAt,
            logId: $c->logId,
        ), $this->log->withLogIds($this->log->modelCalls($from, $to, $purposes)));
    }

    public function speechCalls(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_map(static fn (SpeechCallRecord $c): JournalSpeechCall => new JournalSpeechCall(
            $c->id, $c->voiceId, $c->text, $c->status, $c->audioBytes, $c->occurredAt,
        ), $this->log->speechCalls($from, $to));
    }

    public function clientCalls(array $pathPrefixes, array $exactPaths, ?array $before, int $limit): array
    {
        return array_map(static fn (InboundCallRecord $c): JournalClientCall => new JournalClientCall(
            $c->id, $c->method, $c->path, $c->status, $c->durationMs, $c->requestBytes, $c->responseBytes, $c->userAgent, $c->error, $c->occurredAt,
        ), $this->log->inbound($pathPrefixes, $exactPaths, $before, $limit));
    }

    public function clientCallCount(array $pathPrefixes, array $exactPaths): int
    {
        return $this->log->inboundCount($pathPrefixes, $exactPaths);
    }

    public function firstServed(array $paths): array
    {
        return $this->log->firstServed($paths);
    }
}
