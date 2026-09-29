<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Domain\Check\LessonViolation;

/**
 * WHAT ONE BUILD OF A DAY DID (наряд GEN-4) — every model call it made, every stage's attempt with what its check found, every
 * card sent to a repair and whether the repair helped, every read of the seam judge. The build keeps nothing of it: it rides
 * on the outcome for whoever wants to read the conveyor — the research tools of a наряд — and is dropped.
 */
final class LessonBuildLog
{
    /** @var list<array{stage: string, attempt: int, model: string, prompt_version: string, cost_usd: string, latency_ms: int, tokens_in: int|null, cached_tokens_in: int|null, tokens_out: int|null, call_id: string|null, payload: array<string, mixed>}> */
    public array $calls = [];

    /** @var list<array{stage: string, attempt: int, findings: list<array{code: string, address: string, detail: string}>, fatal: list<string>, off_schema: string|null}> */
    public array $attempts = [];

    /** @var list<array{stage: string, address: string, kind: string, sent_for: list<string>, status: string, kept: bool, broke: list<string>, left: list<string>, helped: bool|null, note: string}> */
    public array $repairs = [];

    /** @var list<array{frames: list<string>, items: int, judged: int, status: string, not_reading: list<string>}> */
    public array $judgements = [];

    public function call(string $stage, int $attempt, ModelReply $reply): void
    {
        $this->calls[] = [
            'stage' => $stage,
            'attempt' => $attempt,
            'model' => $reply->model,
            'prompt_version' => $reply->promptVersion,
            'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs,
            'tokens_in' => $reply->tokensIn,
            'cached_tokens_in' => $reply->cachedTokensIn,
            'tokens_out' => $reply->tokensOut,
            'call_id' => $reply->callId,
            'payload' => $reply->payload,
        ];
    }

    /**
     * @param  list<LessonViolation>  $findings
     * @param  list<LessonViolation>  $fatal
     */
    public function attempt(string $stage, int $attempt, array $findings, array $fatal, ?string $offSchema = null): void
    {
        $this->attempts[] = [
            'stage' => $stage,
            'attempt' => $attempt,
            'findings' => array_map(static fn (LessonViolation $v): array => $v->toArray(), $findings),
            'fatal' => array_values(array_unique(array_map(static fn (LessonViolation $v): string => $v->code, $fatal))),
            'off_schema' => $offSchema,
        ];
    }

    /**
     * @param  list<string>  $sentFor  the codes the card was sent to a repair for
     * @param  list<string>  $broke  the fatal codes the repair would have brought — a repair not kept
     * @param  list<string>  $left  the codes still at the card after a kept repair
     */
    public function repair(string $stage, string $address, string $kind, array $sentFor, string $status, bool $kept, array $broke, array $left, ?bool $helped, string $note = ''): void
    {
        $this->repairs[] = [
            'stage' => $stage, 'address' => $address, 'kind' => $kind, 'sent_for' => $sentFor, 'status' => $status,
            'kept' => $kept, 'broke' => $broke, 'left' => $left, 'helped' => $helped, 'note' => $note,
        ];
    }

    /**
     * @param  list<string>  $frames
     * @param  list<string>  $notReading
     */
    public function judgement(array $frames, int $items, int $judged, string $status, array $notReading): void
    {
        $this->judgements[] = ['frames' => $frames, 'items' => $items, 'judged' => $judged, 'status' => $status, 'not_reading' => $notReading];
    }

    /**
     * A repair's verdict, known only once the seam judge has read its frame again: whether it helped.
     *
     * @param  list<string>  $left  the codes still at the card
     */
    public function helped(string $address, bool $helped, array $left): void
    {
        foreach ($this->repairs as $i => $repair) {
            if ($repair['address'] === $address && $repair['kept']) {
                $this->repairs[$i]['helped'] = $helped;
                $this->repairs[$i]['left'] = array_values(array_unique([...$repair['left'], ...$left]));
            }
        }
    }
}
