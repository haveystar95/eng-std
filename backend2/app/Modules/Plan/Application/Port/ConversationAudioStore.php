<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\ConversationTurnId;
use App\Modules\Plan\Domain\ValueObject\TurnAudio;

/**
 * The sound of the agent's lines — bytes on the SAME private disk the day's voice lives on
 * (`plan.audio_disk`), under a folder of their own.
 *
 * They are not `plan_line_audios` rows and deliberately so: that table is keyed by (scene, line ref,
 * voice) and is the SCENE's bill — what `plan:speak-backfill` owes and `plan:speak-report` counts.
 * A turn's line belongs to one talk, is said once and is never said again; filing it there would
 * make every talk look like a scene that is missing its voice.
 */
interface ConversationAudioStore
{
    /** Writes the bytes and returns what the journal keeps about them. */
    public function put(ConversationId $conversationId, ConversationTurnId $turnId, SpokenAudio $audio): TurnAudio;

    /** The bytes of one turn's line, by the turn's id — what `GET /plans/audio/{id}` serves. */
    public function read(string $turnId): ?string;
}
