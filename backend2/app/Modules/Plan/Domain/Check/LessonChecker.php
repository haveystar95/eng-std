<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Lesson\CountsCheck;
use App\Modules\Plan\Domain\Check\Lesson\ExchangeShapeCheck;
use App\Modules\Plan\Domain\Check\Lesson\MessageLengthCheck;
use App\Modules\Plan\Domain\Check\Lesson\PartnerStatementsCheck;
use App\Modules\Plan\Domain\Check\Lesson\PhraseIdAbsentCheck;
use App\Modules\Plan\Domain\Check\Lesson\PhraseUnusedCheck;
use App\Modules\Plan\Domain\Check\Lesson\PronunciationScriptCheck;
use App\Modules\Plan\Domain\Check\Lesson\SecondMessageQuestionCheck;
use App\Modules\Plan\Domain\Check\Lesson\SpeakingKeySubstringCheck;
use App\Modules\Plan\Domain\Check\Lesson\VariantLengthCheck;
use App\Modules\Plan\Domain\Check\Lesson\VocabularyContainedCheck;
use App\Modules\Plan\Domain\Check\Lesson\VocabularyIdAbsentCheck;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Domain\ValueObject\Finding;

/**
 * Runs every lesson check in the mode it is configured with (`docs/plan-v2.md` §5).
 *
 * Every check runs, every firing is a finding; only the mode decides whether the lesson is kept
 * as it is (observe), corrected (drop) or refused (gate). A gated report still carries the whole
 * list — the retry quotes it — and a check runs on the lesson as the checks BEFORE it left it, so
 * a dropped phrase is not also reported as an unused one.
 */
final readonly class LessonChecker
{
    /** @var list<LessonCheck> */
    private array $checks;

    /** @param list<LessonCheck>|null $checks */
    public function __construct(private CheckModes $modes, ?array $checks = null)
    {
        $this->checks = $checks ?? self::all();
    }

    /** @return list<LessonCheck> in the order they run */
    public static function all(): array
    {
        return [
            new CountsCheck,
            new ExchangeShapeCheck,
            new SecondMessageQuestionCheck,
            new SpeakingKeySubstringCheck,
            new PronunciationScriptCheck,
            new VariantLengthCheck,
            new VocabularyContainedCheck,
            new VocabularyIdAbsentCheck,
            new PhraseIdAbsentCheck,
            new PhraseUnusedCheck,
            new MessageLengthCheck,
            new PartnerStatementsCheck,
        ];
    }

    /** @return CheckReport<Lesson> */
    public function run(Lesson $lesson, LessonContext $context): CheckReport
    {
        $findings = [];
        $gated = false;

        foreach ($this->checks as $check) {
            $violations = $check->violations($lesson, $context);
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
                $lesson = $check->drop($lesson, $context);
            }
        }

        return new CheckReport($lesson, $findings, $gated);
    }
}
