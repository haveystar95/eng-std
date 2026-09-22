<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Entity\ConversationTurn;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\ValueObject\ConversationEnd;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\ConversationType;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;
use App\Modules\Plan\Domain\ValueObject\TurnCost;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The talks and their lines. The row of the talk is written whole every time (its state, its money,
 * its checkpoints); its LINES are only ever inserted — there is no update and no delete for a line
 * anywhere in this class, which is what «append-only» means here rather than a promise in a comment.
 *
 * Reads follow the three indexes the migration builds: the open talk of a day (partial unique),
 * the latest talk of a day (`day_id, started_at DESC`), and a talk's lines by `(conversation_id,
 * turn_index)`.
 */
final class EloquentConversationRepository implements ConversationRepository
{
    public function find(ConversationId $id, UserId $userId): ?Conversation
    {
        $row = ConversationModel::query()->where('id', $id->value)->where('user_id', $userId->value)->first();

        return $row === null ? null : $this->toDomain($row, $this->turnsOf($row->id));
    }

    public function findById(ConversationId $id): ?Conversation
    {
        $row = ConversationModel::query()->where('id', $id->value)->first();

        return $row === null ? null : $this->toDomain($row, $this->turnsOf($row->id));
    }

    public function replaysSince(PlanDayId $dayId, ConversationId $walked, DateTimeImmutable $walkedAt, DateTimeImmutable $since): int
    {
        // The moment is written with its offset: a bare «Y-m-d H:i:s» would be read in the session's zone.
        $from = max($walkedAt, $since)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:sP');

        return ConversationModel::query()
            ->where('day_id', $dayId->value)
            ->where('started_at', '>=', $from)
            ->where('id', '<>', $walked->value)
            ->count();
    }

    public function walkedWithoutPassage(): array
    {
        $rows = ConversationModel::query()
            ->where('state', ConversationState::Ended->value)
            ->whereIn('ended_reason', [ConversationEnd::Natural->value, ConversationEnd::Limit->value, ConversationEnd::Declined->value])
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')->from('plan_stage_passages')
                    ->whereColumn('plan_stage_passages.day_id', 'conversations.day_id')
                    ->where('plan_stage_passages.stage', 'conversation');
            })
            ->orderBy('ended_at')
            ->orderBy('id')
            ->get();

        return array_values($rows->map(fn (ConversationModel $row): Conversation => $this->toDomain($row, []))->all());
    }

    public function openForDay(PlanDayId $dayId): ?Conversation
    {
        $row = ConversationModel::query()
            ->where('day_id', $dayId->value)
            ->where('state', '<>', ConversationState::Ended->value)
            ->first();

        return $row === null ? null : $this->toDomain($row, $this->turnsOf($row->id));
    }

    public function latestForDay(PlanDayId $dayId): ?Conversation
    {
        // `started_at` keeps whole seconds: two talks of one second are told apart by their ids, which are ULIDs —
        // ordered by the millisecond they were made in.
        $row = ConversationModel::query()
            ->where('day_id', $dayId->value)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->first();

        return $row === null ? null : $this->toDomain($row, $this->turnsOf($row->id));
    }

    public function latestForDays(array $dayIds): array
    {
        if ($dayIds === []) {
            return [];
        }
        $ids = array_map(static fn (PlanDayId $id): string => $id->value, $dayIds);
        $rows = ConversationModel::query()->whereIn('day_id', $ids)->orderBy('started_at')->orderBy('id')->get();

        $out = [];
        foreach ($rows as $row) {
            // Ordered ascending, so the last write per day is the latest one.
            $out[$row->day_id] = $this->toDomain($row, []);
        }

        return $out;
    }

    public function lockState(ConversationId $id): ?array
    {
        $row = ConversationModel::query()->where('id', $id->value)->lockForUpdate()->first();
        if ($row === null) {
            return null;
        }

        return [
            'state' => ConversationState::from($row->state),
            'turns' => ConversationTurnModel::query()->where('conversation_id', $id->value)->count(),
        ];
    }

    public function save(Conversation $conversation): void
    {
        ConversationModel::query()->updateOrCreate(['id' => $conversation->id()->value], [
            'user_id' => $conversation->userId()->value,
            'plan_id' => $conversation->planId()->value,
            'day_id' => $conversation->dayId()->value,
            'day_number' => $conversation->dayNumber(),
            'type' => $conversation->type()->value,
            'state' => $conversation->state()->value,
            'scene_ids' => $conversation->sceneIds(),
            'checkpoints_done' => $conversation->checkpointsDone(),
            'turn_limit' => $conversation->turnLimit(),
            'hints_enabled' => $conversation->hintsEnabled(),
            'cost_usd' => $conversation->costUsd(),
            'ended_reason' => $conversation->endedReason()?->value,
            'started_at' => $conversation->startedAt(),
            'ended_at' => $conversation->endedAt(),
        ]);

        $stored = ConversationTurnModel::query()
            ->where('conversation_id', $conversation->id()->value)
            ->pluck('id')
            ->all();
        $known = array_fill_keys(array_map(strval(...), $stored), true);

        foreach ($conversation->turns() as $turn) {
            if (isset($known[$turn->id->value])) {
                continue;
            }
            ConversationTurnModel::query()->create([
                'id' => $turn->id->value,
                'conversation_id' => $conversation->id()->value,
                'user_id' => $conversation->userId()->value,
                'turn_index' => $turn->index,
                'kind' => $turn->kind->value,
                'speaker' => $turn->speaker()->value,
                'text_target' => $turn->textTarget,
                'text_native' => $turn->textNative,
                'audio_path' => $turn->audio?->path,
                'audio_format' => $turn->audio?->format,
                'audio_duration_ms' => $turn->audio?->durationMs,
                'audio_voice_key' => $turn->audio?->voiceKey,
                'audio_characters' => $turn->audio?->characters,
                'audio_credits' => $turn->audio?->credits,
                'audio_cost_usd' => $turn->audio?->costUsd,
                'audio_request_id' => $turn->audio?->requestId,
                'understood' => $turn->understood,
                'phrases_used' => $turn->phrasesUsed,
                'off_topic' => $turn->offTopic,
                'checkpoint_done' => $turn->checkpointDone,
                'hint_native' => $turn->hintNative,
                'opens_target' => $turn->opensTarget,
                'model' => $turn->cost->model,
                'prompt_version' => $turn->cost->promptVersion,
                'tokens_in' => $turn->cost->tokensIn,
                'tokens_out' => $turn->cost->tokensOut,
                'model_cost_usd' => $turn->cost->modelCostUsd,
                'speech_cost_usd' => $turn->cost->speechCostUsd,
                'cost_usd' => $turn->cost->totalUsd(),
                'model_latency_ms' => $turn->cost->modelLatencyMs,
                'speech_latency_ms' => $turn->cost->speechLatencyMs,
                'latency_ms' => $turn->cost->latencyMs,
                'created_at' => $turn->createdAt,
            ]);
        }
    }

    /** @return list<ConversationTurn> */
    private function turnsOf(string $conversationId): array
    {
        $rows = ConversationTurnModel::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('turn_index')
            ->get();

        return array_values($rows->map($this->turnToDomain(...))->all());
    }

    private function turnToDomain(ConversationTurnModel $row): ConversationTurn
    {
        $audio = $row->audio_path === null ? null : new TurnAudio(
            path: $row->audio_path,
            format: (string) ($row->audio_format ?? 'mp3'),
            durationMs: $row->audio_duration_ms,
            voiceKey: (string) ($row->audio_voice_key ?? ''),
            characters: (int) ($row->audio_characters ?? 0),
            credits: (int) ($row->audio_credits ?? 0),
            costUsd: (string) ($row->audio_cost_usd ?? '0.000000'),
            requestId: $row->audio_request_id,
        );

        /** @var list<string> $phrases */
        $phrases = array_values(array_filter($row->phrases_used ?? [], is_string(...)));

        return ConversationTurn::reconstitute(
            id: ConversationTurnId::fromString($row->id),
            conversationId: ConversationId::fromString($row->conversation_id),
            index: $row->turn_index,
            kind: TurnKind::from($row->kind),
            textTarget: $row->text_target,
            textNative: $row->text_native,
            audio: $audio,
            understood: $row->understood,
            phrasesUsed: $phrases,
            offTopic: $row->off_topic,
            checkpointDone: $row->checkpoint_done,
            hintNative: $row->hint_native,
            cost: new TurnCost(
                modelCostUsd: $row->model_cost_usd,
                speechCostUsd: $row->speech_cost_usd,
                modelLatencyMs: (int) ($row->model_latency_ms ?? 0),
                speechLatencyMs: (int) ($row->speech_latency_ms ?? 0),
                latencyMs: (int) ($row->latency_ms ?? 0),
                model: $row->model,
                promptVersion: $row->prompt_version,
                tokensIn: $row->tokens_in,
                tokensOut: $row->tokens_out,
            ),
            createdAt: new DateTimeImmutable($row->created_at),
            opensTarget: $row->opens_target,
        );
    }

    /** @param list<ConversationTurn> $turns */
    private function toDomain(ConversationModel $row, array $turns): Conversation
    {
        /** @var list<string> $sceneIds */
        $sceneIds = array_values(array_filter($row->scene_ids ?? [], is_string(...)));
        /** @var list<string> $done */
        $done = array_values(array_filter($row->checkpoints_done ?? [], is_string(...)));

        return Conversation::reconstitute(
            id: ConversationId::fromString($row->id),
            planId: PlanId::fromString($row->plan_id),
            userId: UserId::fromString($row->user_id),
            dayId: PlanDayId::fromString($row->day_id),
            dayNumber: $row->day_number,
            type: ConversationType::from($row->type),
            sceneIds: $sceneIds,
            checkpointsDone: $done,
            state: ConversationState::from($row->state),
            turnLimit: $row->turn_limit,
            hintsEnabled: $row->hints_enabled,
            turns: $turns,
            costUsd: $row->cost_usd,
            startedAt: new DateTimeImmutable($row->started_at),
            endedAt: $row->ended_at === null ? null : new DateTimeImmutable($row->ended_at),
            endedReason: $row->ended_reason === null ? null : ConversationEnd::from($row->ended_reason),
        );
    }
}
