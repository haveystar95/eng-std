<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\SceneRunOutcome;

/** Один ход завершённого прогона — какая реплика и чем кончилось. {@see RecordSceneRun} */
final readonly class SceneRunTurn
{
    public function __construct(
        public string $termId,
        public SceneRunOutcome $outcome,
    ) {}
}
