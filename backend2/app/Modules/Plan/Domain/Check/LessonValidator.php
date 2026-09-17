<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Lesson\CheckRules;
use App\Modules\Plan\Domain\Check\Lesson\FillerRules;
use App\Modules\Plan\Domain\Check\Lesson\FrameRules;
use App\Modules\Plan\Domain\Check\Lesson\ImagePromptRules;
use App\Modules\Plan\Domain\Check\Lesson\KindRules;
use App\Modules\Plan\Domain\Check\Lesson\LineRules;
use App\Modules\Plan\Domain\Check\Lesson\ListeningRules;
use App\Modules\Plan\Domain\Check\Lesson\NativeRules;
use App\Modules\Plan\Domain\Check\Lesson\PartnerRules;
use App\Modules\Plan\Domain\Check\Lesson\StoryRules;
use App\Modules\Plan\Domain\Check\Lesson\StructureRules;
use App\Modules\Plan\Domain\Check\Lesson\VisitRules;
use App\Modules\Plan\Domain\Check\Lesson\VocabularyRules;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * THE LESSON VALIDATOR (наряды GEN-2a, GEN-2b, `docs/plan-v2.md` §4).
 *
 * Every rule runs over the model's answer as written, every breach is a finding with a code and a card address.
 * The validator only finds: no finding edits the lesson here. What a finding does to the day — a warning is
 * counted and kept, a fatal one holds the day for a repair — is {@see LessonGate}'s.
 *
 * The rules are about the pair of languages: what a rule needs to know of a language it reads off that language's
 * pack, and a check whose pack is missing does not run — it is written into the context's skips
 * ({@see LessonValidationContext::$skips}), counted as `lang.pack_missing`, never a finding.
 */
final readonly class LessonValidator
{
    /** @var list<LessonRule> */
    private array $rules;

    /** @param list<LessonRule>|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? [
            new StructureRules,
            new VisitRules,
            new FrameRules,
            new FillerRules,
            new LineRules,
            new KindRules,
            new PartnerRules,
            new CheckRules,
            new ListeningRules,
            new VocabularyRules,
            new NativeRules,
            new ImagePromptRules,
            new StoryRules,
        ];
    }

    /** @return list<LessonViolation> */
    public function run(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($this->rules as $rule) {
            $out = [...$out, ...$rule->violations($answer, $context)];
        }

        return $out;
    }

    /**
     * How many findings each code has — every code of {@see LessonCodes::all()}, zeros included.
     *
     * @param  list<LessonViolation>  $violations
     * @return array<string, int>
     */
    public static function tally(array $violations): array
    {
        $out = array_fill_keys(LessonCodes::all(), 0);
        foreach ($violations as $violation) {
            $out[$violation->code] = ($out[$violation->code] ?? 0) + 1;
        }

        return $out;
    }
}
