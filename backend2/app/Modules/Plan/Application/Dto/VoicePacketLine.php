<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * One line of a backfill packet: the scene it belongs to, what is said in which voice, and whether its file is
 * owed — a dialogue is said whole to keep its two voices and its rhythm, and only the missing lines are kept.
 */
final readonly class VoicePacketLine
{
    public function __construct(
        public PlanSceneId $sceneId,
        public LineToSay $line,
        public bool $owed,
    ) {}
}
