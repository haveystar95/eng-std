<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Console;

use App\Modules\Plan\Application\Command\ReviseLesson;
use App\Modules\Plan\Application\Command\ReviseLessonHandler;
use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Domain\Exception\LessonAlreadyDealt;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Console\Command;

/**
 * `plan:repair-card {scene} {address} {--code=*} {--apply}` — P2R by hand (наряд GEN-2a): repair ONE card of
 * a written lesson — a frame `p3`, a learner line `B3`, a check `x3.check`, a listening question `L2` — for
 * what the validator finds at it (or only the named codes).
 *
 * Without `--apply` nothing is written: the card before and after and the findings before and after are
 * printed, and only the model call is spent. With `--apply` the repaired answer replaces the stored one —
 * refused once the scene's day is dealt. The build never calls this.
 */
final class PlanRepairCardCommand extends Command
{
    protected $signature = 'plan:repair-card {scene : scene id} {address : p3 | p3.f2 | B3 | x3.check | L2} {--code=* : only these validator codes} {--apply : write the repaired lesson}';

    protected $description = 'P2R: repair one card of a plan lesson by its address and the validator codes found at it';

    public function handle(LessonCardRepairer $repairer, ReviseLessonHandler $revise): int
    {
        $scene = $this->argument('scene');
        $address = $this->argument('address');
        $sceneId = PlanSceneId::fromString(is_string($scene) ? $scene : '');
        /** @var list<string> $codes */
        $codes = array_values(array_map('strval', (array) $this->option('code')));
        $outcome = $repairer->repair($sceneId, is_string($address) ? $address : '', $codes);

        $this->line("status: {$outcome->status}".($outcome->note !== '' ? " — {$outcome->note}" : ''));
        $this->line("card: {$outcome->address} ({$outcome->kind})  cost: \${$outcome->costUsd}  latency: {$outcome->latencyMs} ms  prompt: {$outcome->promptVersion}");
        $this->line('findings at the card before: '.self::json($outcome->findingsBefore));
        $this->line('before: '.self::json($outcome->before));
        $this->line('after:  '.self::json($outcome->after));
        if ($outcome->status !== LessonCardRepairOutcome::REPAIRED || $outcome->answer === null) {
            return $outcome->status === LessonCardRepairOutcome::OFF_SCHEMA ? self::FAILURE : self::SUCCESS;
        }
        $this->line('findings at the card after: '.self::json($outcome->findingsAfter));
        $this->line("findings in the lesson: {$outcome->lessonFindingsBefore} → ".count($outcome->lessonFindings));

        if (! (bool) $this->option('apply')) {
            $this->line('not written (no --apply)');

            return self::SUCCESS;
        }
        try {
            $revise(new ReviseLesson($sceneId, $outcome->answer, $outcome->lessonFindings, $outcome->costUsd));
        } catch (LessonAlreadyDealt $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line('written');

        return self::SUCCESS;
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
