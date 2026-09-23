<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\ConversationMaterialView;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTalk;
use App\Modules\Plan\Application\Dto\Inspection\InspectedTurn;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * THE PLAN'S TALKS, TURN BY TURN (наряд ADM-1, «Разговоры»): every talk (the day's, the rehearsal's, «Ещё раз»), its
 * constructions — said (credited on a turn: the turn's `phrases_used`, the same rule the talk's summary reads,
 * {@see \App\Modules\Plan\Domain\Service\ConversationOutcomes::heard()}), partly (some of its key words heard in a
 * learner's line, {@see PhraseUse::keyTally()}), or not — and the transcript: who, what, the sound, what recognition heard,
 * which construction the role's line opened, the verdict, the delay and the money of each turn; how it ended.
 * The role's voice is checked against the voice of the scene it was said in — the checkpoint current at that turn,
 * as the move itself picks it.
 */
final readonly class TalkReport
{
    public const ENDINGS = [
        'natural' => 'прощание',
        'limit' => 'лимит ходов, минут или денег',
        'declined' => 'ученик отказался',
        'replayed' => 'прерван «Ещё раз»',
    ];

    public function __construct(
        private ConversationMaterial $material,
        private LanguagePacks $packs,
        private LineSpeaker $speaker,
        private VoiceTable $voices,
        private PhraseUse $phrases = new PhraseUse,
    ) {}

    /** @return list<array<string, mixed>> */
    public function of(PlanInspectionData $data, ?int $number): array
    {
        $out = [];
        foreach ($data->talks() as $talk) {
            if ($number !== null && $talk->dayNumber !== $number) {
                continue;
            }
            $out[] = $this->talk($data, $talk);
        }

        return $out;
    }

    /**
     * Each role line with a sound: its turn, the voice it was said in, and the voice its scene's cast gives the role.
     *
     * @return list<array{turn: int, voice: string, expected: string|null}>
     */
    public function voicedRoleLines(PlanInspectionData $data, InspectedTalk $talk): array
    {
        $material = $this->materialOf($data, $talk);
        $out = [];
        foreach ($this->expectedVoices($data, $talk, $material) as $index => $expected) {
            foreach ($talk->turns as $turn) {
                if ($turn->index === $index && $turn->audioVoiceKey !== null) {
                    $out[] = ['turn' => $index, 'voice' => $turn->audioVoiceKey, 'expected' => $expected];
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function talk(PlanInspectionData $data, InspectedTalk $talk): array
    {
        $material = $this->materialOf($data, $talk);
        $lang = $data->plan->targetLang()->value;
        $identities = $this->voices->identities($lang);
        $expected = $this->expectedVoices($data, $talk, $material);

        return [
            'id' => $talk->id,
            'day' => $talk->dayNumber,
            'type' => $talk->type,
            'state' => $talk->state,
            'ended_reason' => $talk->endedReason,
            'ended_label' => $talk->endedReason === null ? null : (self::ENDINGS[$talk->endedReason] ?? $talk->endedReason),
            'started_at' => $talk->startedAt->format(DATE_ATOM),
            'ended_at' => $talk->endedAt?->format(DATE_ATOM),
            'turn_limit' => $talk->turnLimit,
            'hints_enabled' => $talk->hintsEnabled,
            'cost_usd' => (float) $talk->costUsd,
            'scenes' => array_map(static fn ($c): array => [
                'scene_id' => $c->sceneId,
                'title_native' => $c->titleNative,
                'role_native' => $c->roleNative,
                'partner_gender' => $c->partnerGender->value,
                'done' => in_array($c->sceneId, $talk->checkpointsDone, true),
            ], $material->checkpoints),
            'checkpoints_done' => $talk->checkpointsDone,
            'targets' => $this->targets($talk, $material),
            'turns' => array_map(fn (InspectedTurn $turn): array => [
                'index' => $turn->index,
                'kind' => $turn->kind,
                'speaker' => $turn->speaker,
                'text_target' => $turn->textTarget,
                'text_native' => $turn->textNative,
                'heard' => $turn->speaker === Speaker::Learner->value ? $turn->textTarget : null,
                'audio_id' => $turn->hasAudio ? $turn->id : null,
                'audio' => $turn->hasAudio ? [
                    'voice' => VoiceLine::voice($turn->audioVoiceKey, $identities),
                    'expected_voice' => VoiceLine::voice($expected[$turn->index] ?? null, $identities),
                    'duration_ms' => $turn->audioDurationMs,
                    'characters' => $turn->audioCharacters,
                    'credits' => $turn->audioCredits,
                    'cost_usd' => $turn->audioCostUsd,
                ] : null,
                'understood' => $turn->understood,
                'off_topic' => $turn->offTopic,
                'phrases_used' => $turn->phrasesUsed,
                'opens_target' => $turn->opensTarget,
                'checkpoint_done' => $turn->checkpointDone,
                'hint_native' => $turn->hintNative,
                'model' => $turn->model,
                'prompt_version' => $turn->promptVersion,
                'tokens_in' => $turn->tokensIn,
                'tokens_out' => $turn->tokensOut,
                'model_cost_usd' => (float) $turn->modelCostUsd,
                'speech_cost_usd' => (float) $turn->speechCostUsd,
                'cost_usd' => (float) $turn->costUsd,
                'latency_ms' => $turn->latencyMs,
                'model_latency_ms' => $turn->modelLatencyMs,
                'speech_latency_ms' => $turn->speechLatencyMs,
                'created_at' => $turn->createdAt->format(DATE_ATOM),
            ], $talk->turns),
            'not_stored' => ['сырой текст распознавания и его альтернативы — сервер получает только итоговую строку heard'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function targets(InspectedTalk $talk, ConversationMaterialView $material): array
    {
        $pack = $this->packs->for($material->targetLang);

        return array_map(function (ConversationPhrase $target) use ($talk, $pack): array {
            $saidOn = null;
            $best = ['found' => 0, 'total' => 0, 'turn' => null];
            foreach ($talk->turns as $turn) {
                if ($saidOn === null && in_array($target->id(), $turn->phrasesUsed, true)) {
                    $saidOn = $turn->index;
                }
                if ($turn->kind === 'said' && $turn->textTarget !== null) {
                    $tally = $this->phrases->keyTally($turn->textTarget, $target, $pack);
                    if ($tally['found'] > $best['found']) {
                        $best = [...$tally, 'turn' => $turn->index];
                    }
                }
            }

            return [
                'scene_id' => $target->sceneId,
                'ref' => $target->ref,
                'frame_target' => $target->frameTarget,
                'frame_native' => $target->frameNative,
                'example_target' => $target->exampleTarget,
                'status' => $saidOn !== null ? 'said' : ($best['found'] > 0 ? 'partial' : 'none'),
                'said_turn' => $saidOn,
                'key_words' => ['found' => $best['found'], 'total' => $best['total'], 'turn' => $best['turn']],
                'opened_on_turn' => self::openedOn($talk, $target),
            ];
        }, $material->targets);
    }

    private static function openedOn(InspectedTalk $talk, ConversationPhrase $target): ?int
    {
        foreach ($talk->turns as $turn) {
            if ($turn->opensTarget !== null && in_array($turn->opensTarget, [$target->id(), $target->ref], true)) {
                return $turn->index;
            }
        }

        return null;
    }

    /**
     * The voice key each role line should have been said in — the partner's voice of the scene current at that turn.
     *
     * @return array<int, string|null>
     */
    private function expectedVoices(PlanInspectionData $data, InspectedTalk $talk, ConversationMaterialView $material): array
    {
        $lang = $data->plan->targetLang()->value;
        $done = [];
        $out = [];
        foreach ($talk->turns as $turn) {
            if ($turn->speaker === Speaker::Partner->value) {
                // The talk's current scene as `Conversation::currentCheckpoint()` reads it — the first of ITS scenes not
                // walked — and that scene's checkpoint as the move picks it: none left means the LAST one
                // ({@see ConversationMaterialView::checkpoint()}), never the first.
                $current = null;
                foreach ($talk->sceneIds as $sceneId) {
                    if (! in_array($sceneId, $done, true)) {
                        $current = $sceneId;
                        break;
                    }
                }
                $gender = $material->checkpoint($current)->partnerGender ?? VoiceGender::Female;
                $out[$turn->index] = $this->speaker->voiceKeyFor($lang, Speaker::Partner, $gender);
            }
            if ($turn->checkpointDone !== null) {
                $done[] = $turn->checkpointDone;
            }
        }

        return $out;
    }

    private function materialOf(PlanInspectionData $data, InspectedTalk $talk): ConversationMaterialView
    {
        $day = $data->dayByNumber($talk->dayNumber);

        return $day === null ? new ConversationMaterialView([], []) : $this->material->for($data->plan, $day);
    }
}
