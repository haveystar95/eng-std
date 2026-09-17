<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\PackSkips;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * What the lesson was ORDERED with — the counts, the pair of languages as their packs, the learner's gender the rules
 * compare against, the story so far (the earlier days of the plan) and the partner's role of this scene (наряд GEN-3) —
 * and what the rules could not check for want of a pack (наряд GEN-2b).
 *
 * A rule that needs a language asks {@see reads()} first: the pack of that side has every key the check needs —
 * the check runs; it does not — the check is skipped, the skip is written down ({@see $skips}) and nothing is
 * found. Skips are counted as `lang.pack_missing`, never as findings.
 */
final readonly class LessonValidationContext
{
    public PackSkips $skips;

    public function __construct(
        public int $vocabularyCount,
        public int $dialogueCount,
        public LanguagePack $native,
        public LanguagePack $target,
        public ?VoiceGender $learnerGender = null,
        public EarlierDays $earlierDays = new EarlierDays,
        public string $partnerRoleTarget = '',
    ) {
        $this->skips = new PackSkips;
    }

    /** Does the pack of `$side` have every key `$code` needs? When not, the check is written down as skipped. */
    public function reads(string $code, LanguageSide $side, string ...$keys): bool
    {
        $pack = $side === LanguageSide::Target ? $this->target : $this->native;
        $missing = array_values(array_filter($keys, static fn (string $key): bool => ! $pack->has($key)));
        if ($missing === []) {
            return true;
        }
        $this->skips->record($code, $side, $pack->code, $missing);

        return false;
    }

    /** The target's word rules — ask {@see reads()} for their keys first. */
    public function targetWords(): LanguageWords
    {
        return new LanguageWords($this->target);
    }

    /** The learner's language's word rules — ask {@see reads()} for their keys first. */
    public function nativeWords(): LanguageWords
    {
        return new LanguageWords($this->native);
    }
}
