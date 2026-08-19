<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Dto\BakeoffCallResult;
use App\Modules\Generation\Application\Port\BakeoffJournal;
use App\Modules\Generation\Domain\ValueObject\CandidateVerdict;
use App\Modules\Generation\Domain\ValueObject\CheckId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

/**
 * The sandbox writer. Three tables, all of them new, none of them read by anything that serves a
 * learner — and no statement in this class names a content table.
 */
final readonly class EloquentBakeoffJournal implements BakeoffJournal
{
    public function openRun(string $label, string $promptVersion, string $sourceLang, string $targetLang, array $notes): string
    {
        $id = Ulid::generate();

        DB::table('bakeoff_runs')->insert([
            'id' => $id,
            'label' => mb_substr($label, 0, 64),
            'prompt_version' => $promptVersion,
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'notes' => json_encode($notes, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        return $id;
    }

    public function recordCall(string $runId, BakeoffCallResult $result): string
    {
        $callId = Ulid::generate();

        DB::table('bakeoff_calls')->insert([
            'id' => $callId,
            'run_id' => $runId,
            'track' => $result->track->value,
            'provider' => $result->provider->value,
            'model' => mb_substr($result->model, 0, 64),
            'shape' => $result->track->shape()->value,
            'prompt_sha' => $result->promptSha,
            'task_key' => $result->taskKey,
            'latency_ms' => $result->latencyMs,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'cost_usd' => $result->costUsd,
            'ok' => $result->ok,
            'error' => $result->error,
            'created_at' => now(),
        ]);

        if ($result->batch === null) {
            return $callId;
        }

        $rows = [];
        foreach ($result->batch->verdicts as $verdict) {
            $rows[] = [
                'id' => Ulid::generate(),
                'run_id' => $runId,
                'call_id' => $callId,
                'track' => $result->track->value,
                'provider' => $result->provider->value,
                'position' => $verdict->item->position,
                'source_term_id' => $verdict->item->sourceTermId,
                'term_text' => $verdict->item->givenTerm ?? $verdict->item->text,
                'payload' => json_encode($this->payload($verdict), JSON_UNESCAPED_UNICODE),
                'checks' => json_encode([
                    'failed' => array_map(static fn (CheckId $c): string => $c->value, $verdict->failed),
                    'notes' => $verdict->notes,
                ], JSON_UNESCAPED_UNICODE),
                'clean' => $verdict->isClean(),
                'created_at' => now(),
            ];
        }

        // One statement: a run writes hundreds of candidates and a row-at-a-time insert would make
        // the journal slower than the model calls it is recording.
        if ($rows !== []) {
            DB::table('bakeoff_candidates')->insert($rows);
        }

        return $callId;
    }

    /** @return array<string, mixed> */
    private function payload(CandidateVerdict $verdict): array
    {
        $item = $verdict->item;

        return [
            'text' => $item->text,
            'type' => $item->type,
            'translation' => $item->translation,
            'example' => $item->example,
            'example_translation' => $item->exampleTranslation,
            'transcription' => $item->transcription,
            'cefr' => $item->cefr,
            'options' => $item->options,
        ];
    }
}
