<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue;

use App\Modules\Plan\Domain\Check\Dialogue\Rule\CheckAnswerIsFiller;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\CheckMissing;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\CheckShape;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\CheckVerbatim;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\DialogueCount;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\ExchangeShape;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\FrameRepeated;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\FrameUnused;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\LineForeignFiller;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\LineNeFrame;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\LineTooLong;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\LineUnknownFrame;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\ListeningCount;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\ListeningDistractorNotFiller;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\ListeningShape;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\NativeForeignLetters;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\PartnerChanged;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\PartnerMissing;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\PartnerTwice;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\PartnerUnlinked;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\RescueCount;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\SpeakingKeyWrong;
use App\Modules\Plan\Domain\Check\Dialogue\Rule\VariantLonger;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * THE CHECK OF THE DIALOGUE (наряд GEN-4, 3.6) — code only: the dialogue read against the skeleton it was written from, byte
 * for byte where the skeleton fixes it (every partner line said once as spelled, every learner line its frame with its filler).
 * A FATAL finding asks the dialogue once more; a warning sends its card — an exchange, a check, a listening question — to a
 * repair. The rules, fatal first, in the order of the order; since GEN-4b `frame.repeated` beside them (a frame said twice only
 * to a remainder line or to a line paired with it).
 */
final readonly class DialogueCheck
{
    /** @var list<DialogueRule> */
    private array $rules;

    /** @param list<DialogueRule>|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::rules();
    }

    /** @return list<DialogueRule> */
    public static function rules(): array
    {
        return [
            new DialogueCount,
            new ExchangeShape,
            new PartnerMissing,
            new PartnerTwice,
            new PartnerChanged,
            new PartnerUnlinked,
            new LineNeFrame,
            new LineUnknownFrame,
            new LineForeignFiller,
            new FrameUnused,
            new FrameRepeated,
            new RescueCount,
            new CheckMissing,
            new CheckShape,
            new ListeningCount,
            new ListeningShape,
            new CheckAnswerIsFiller,
            new CheckVerbatim,
            new NativeForeignLetters,
            new ListeningDistractorNotFiller,
            new LineTooLong,
            new SpeakingKeyWrong,
            new VariantLonger,
        ];
    }

    /** @return list<LessonViolation> */
    public function run(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        foreach ($this->rules as $rule) {
            $out = [...$out, ...$rule->findings($dialogue, $context)];
        }

        return $out;
    }
}
