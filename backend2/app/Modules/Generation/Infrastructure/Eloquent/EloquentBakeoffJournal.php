<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Dto\BakeoffCallResult;
use App\Modules\Generation\Application\Port\BakeoffJournal;
use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Domain\ValueObject\BakeoffTrack;
use App\Modules\Generation\Domain\ValueObject\CandidateItem;
use App\Modules\Generation\Domain\ValueObject\CandidateVerdict;
use App\Modules\Generation\Domain\ValueObject\CheckedBatch;
use App\Modules\Generation\Domain\ValueObject\CheckId;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;
use stdClass;

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

    public function readRun(string $runId): ?array
    {
        $run = DB::table('bakeoff_runs')->where('id', $runId)->first();
        if ($run === null) {
            return null;
        }

        $candidates = [];
        foreach (DB::table('bakeoff_candidates')->where('run_id', $runId)->orderBy('position')->get() as $row) {
            $candidates[$row->call_id][] = $row;
        }

        $results = [];
        foreach (DB::table('bakeoff_calls')->where('run_id', $runId)->orderBy('created_at')->get() as $call) {
            $track = BakeoffTrack::from($call->track);
            $provider = ProviderId::from($call->provider);

            if (! $call->ok) {
                $results[] = BakeoffCallResult::failed(
                    $track, $provider, $call->model, $call->task_key, $call->prompt_sha,
                    (string) ($call->error ?? ''), $call->latency_ms,
                );

                continue;
            }

            $verdicts = [];
            foreach ($candidates[$call->id] ?? [] as $row) {
                $verdicts[] = $this->verdict($row);
            }

            // The size verdict is re-derived rather than stored: it is a function of the item count
            // and the run's requested size, and a stored copy could disagree with the rows beside it.
            $expected = $this->expectedSize($run, $track);
            $short = $expected !== null && count($verdicts) !== $expected;

            $results[] = BakeoffCallResult::answered(
                $track, $provider, $call->model, $call->task_key, $call->prompt_sha,
                new CheckedBatch(
                    $verdicts,
                    $short ? [CheckId::Size] : [],
                    $short ? 'запрошено ' . $expected . ', получено ' . count($verdicts) : null,
                ),
                new ModelAnswer(
                    payload: [],
                    model: $call->model,
                    latencyMs: (int) ($call->latency_ms ?? 0),
                    tokensIn: $call->tokens_in,
                    tokensOut: $call->tokens_out,
                    costUsd: $call->cost_usd !== null ? (string) $call->cost_usd : null,
                ),
            );
        }

        return [
            'results' => $results,
            'run' => [
                'id' => $run->id,
                'label' => $run->label,
                'prompt_version' => $run->prompt_version,
                'source_lang' => $run->source_lang,
                'target_lang' => $run->target_lang,
                'notes' => json_decode((string) $run->notes, true),
                'created_at' => $run->created_at,
            ],
        ];
    }

    public function readCores(string $runId, ?string $provider = null, ?string $track = null): array
    {
        $query = DB::table('bakeoff_candidates')->where('run_id', $runId);
        if ($provider !== null) {
            $query->where('provider', $provider);
        }
        if ($track !== null) {
            $query->where('track', $track);
        }

        $cores = [];
        foreach ($query->orderBy('call_id')->orderBy('position')->get() as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $text = is_string($payload['text'] ?? null) ? trim($payload['text']) : '';
            if ($text === '') {
                continue;
            }

            // A core with no translation or example is not a core — handing it to the machinery
            // stage would ask that stage to build options against a blank, and the resulting
            // failure would be recorded against the wrong half of the pipeline.
            $translation = is_string($payload['translation'] ?? null) ? trim($payload['translation']) : '';
            $example = is_string($payload['example'] ?? null) ? trim($payload['example']) : '';
            if ($translation === '' || $example === '') {
                continue;
            }

            $cores[] = [
                'id' => (string) $row->id,
                'text' => $text,
                'translation' => $translation,
                'example' => $example,
                'example_translation' => is_string($payload['example_translation'] ?? null)
                    ? trim($payload['example_translation'])
                    : '',
            ];
        }

        return $cores;
    }

    /** How many items this track's call should have had, from the run's own notes. */
    private function expectedSize(stdClass $run, BakeoffTrack $track): ?int
    {
        if ($track === BakeoffTrack::Enrichment) {
            return 1; // one call, one given term
        }
        $notes = json_decode((string) $run->notes, true);

        return is_array($notes) && is_int($notes['size'] ?? null) ? $notes['size'] : null;
    }

    private function verdict(stdClass $row): CandidateVerdict
    {
        $payload = json_decode((string) $row->payload, true);
        $checks = json_decode((string) $row->checks, true);
        $payload = is_array($payload) ? $payload : [];
        $checks = is_array($checks) ? $checks : [];

        $failed = [];
        foreach (is_array($checks['failed'] ?? null) ? $checks['failed'] : [] as $value) {
            if (is_string($value) && ($check = CheckId::tryFrom($value)) !== null) {
                $failed[] = $check;
            }
        }

        $options = [];
        foreach (is_array($payload['options'] ?? null) ? $payload['options'] : [] as $option) {
            if (is_string($option)) {
                $options[] = $option;
            }
        }

        return new CandidateVerdict(
            new CandidateItem(
                position: (int) $row->position,
                text: is_string($payload['text'] ?? null) ? $payload['text'] : '',
                type: is_string($payload['type'] ?? null) ? $payload['type'] : null,
                translation: is_string($payload['translation'] ?? null) ? $payload['translation'] : null,
                example: is_string($payload['example'] ?? null) ? $payload['example'] : null,
                exampleTranslation: is_string($payload['example_translation'] ?? null) ? $payload['example_translation'] : null,
                transcription: is_string($payload['transcription'] ?? null) ? $payload['transcription'] : null,
                cefr: is_string($payload['cefr'] ?? null) ? $payload['cefr'] : null,
                options: $options,
                givenTerm: is_string($row->term_text) ? $row->term_text : null,
                sourceTermId: is_string($row->source_term_id) ? $row->source_term_id : null,
            ),
            $failed,
            is_array($checks['notes'] ?? null) ? $checks['notes'] : [],
        );
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
