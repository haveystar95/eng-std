<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\Blueprint\CharLimitsCheck;
use App\Modules\Plan\Domain\Check\Blueprint\GoalsCountCheck;
use App\Modules\Plan\Domain\Check\Blueprint\PlanShapeCheck;
use App\Modules\Plan\Domain\Check\Blueprint\PrioritiesCheck;
use App\Modules\Plan\Domain\Check\Blueprint\TopicPartsCheck;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Domain\ValueObject\Finding;

/** The plan builder's checks, run like the lesson's (`docs/plan-v2.md` §5). An `unclear` answer has nothing to check. */
final readonly class BlueprintChecker
{
    /** @var list<BlueprintCheck> */
    private array $checks;

    /** @param list<BlueprintCheck>|null $checks */
    public function __construct(private CheckModes $modes, ?array $checks = null)
    {
        $this->checks = $checks ?? self::all();
    }

    /** @return list<BlueprintCheck> */
    public static function all(): array
    {
        return [
            new PlanShapeCheck,
            new PrioritiesCheck,
            new TopicPartsCheck,
            new GoalsCountCheck,
            new CharLimitsCheck,
        ];
    }

    /** @return CheckReport<Blueprint> */
    public function run(Blueprint $blueprint, BlueprintContext $context): CheckReport
    {
        if ($blueprint->isUnclear()) {
            return new CheckReport($blueprint, [], false);
        }

        $findings = [];
        $gated = false;
        foreach ($this->checks as $check) {
            $violations = $check->violations($blueprint, $context);
            if ($violations === []) {
                continue;
            }
            $mode = $check->switchable() ? $this->modes->for($check->name()) : CheckMode::Observe;
            foreach ($violations as $detail) {
                $findings[] = new Finding($check->name(), $mode, CheckAction::for($mode), $detail);
            }
            if ($mode === CheckMode::Gate) {
                $gated = true;
            } elseif ($mode === CheckMode::Drop) {
                $blueprint = $check->drop($blueprint, $context);
            }
        }

        return new CheckReport($blueprint, $findings, $gated);
    }
}
