<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Domain\ValueObject\ModelCall;

/**
 * THE BILL OF ONE DAY'S BUILD (наряд GEN-4): what every call of it cost and took — both stages with their repeats, the
 * repairs, the seam judge — how many times a stage was asked, and which models wrote the stages. What the scene's lesson is
 * stamped with ({@see ModelCall}).
 */
final class LessonBill
{
    public string $costUsd = '0.000000';

    public int $latencyMs = 0;

    /** How many times the two stages were asked, their repeats included — the lesson's `attempts`. */
    public int $stageCalls = 0;

    /** @var list<string> the models that wrote the stages, each once, in the order they wrote */
    private array $models = [];

    public function stage(ModelReply $reply): void
    {
        $this->add($reply->costUsd, $reply->latencyMs);
        $this->stageCalls++;
        if (! in_array($reply->model, $this->models, true)) {
            $this->models[] = $reply->model;
        }
    }

    public function repair(LessonCardRepairOutcome $outcome): void
    {
        $this->add($outcome->costUsd, $outcome->latencyMs);
    }

    public function judge(LessonSeamVerdict $verdict): void
    {
        $this->add($verdict->costUsd, $verdict->latencyMs);
    }

    /** The models of the two stages — one name when one model wrote both, «skeleton's+dialogue's» otherwise. */
    public function models(): string
    {
        return implode('+', $this->models);
    }

    private function add(string $costUsd, int $latencyMs): void
    {
        $this->costUsd = ModelCall::addCosts($this->costUsd, $costUsd);
        $this->latencyMs += $latencyMs;
    }
}
