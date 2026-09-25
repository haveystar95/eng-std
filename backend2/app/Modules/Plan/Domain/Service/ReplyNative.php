<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;

/**
 * THE GUARD OF THE ROLE'S TRANSLATION (наряд FIX-4c §6). The role answers its line twice — in the language the learner
 * studies (`reply_target`) and in the learner's own (`reply_native`), the grey line under the bubble. A mini model now
 * and then sends the second in English — the first line again (the FIX-4b rehearsal: «How long has he had these
 * symptoms?» under «How long has he had these symptoms?») — and then the learner has no translation at all.
 *
 * `reply_native` is missing when it is empty, when it says the same words as `reply_target` (case, marks and spaces
 * aside), or when fewer than half of its letters are the learner's language's own letters — as the pack of that language
 * writes them (`script_letters`: a Russian line may name «Ибупрофен» or «MRI» in Latin letters and still be Russian). A
 * language whose pack does not write its letters is judged by the first two only.
 *
 * What it cannot see is a translation that is Russian and wrong — «Please tell me his temperature» said back as «какая у
 * него самая высокая температура»: that one is the prompt's (v3.3, OUTPUT), not the code's.
 */
final class ReplyNative
{
    /** The REDO reason the role is asked again with, and the rejection's reason in the journal. */
    public const REDO = 'native_missing';

    /** Counted on the prompt's version: an answer asked again for its translation. */
    public const CODE = 'conversation.native_missing';

    /** Counted when the second answer has none either: its line is said with no translation (`reply_native` = ""). */
    public const CODE_BLANKED = 'conversation.native_missing_blanked';

    public static function missing(string $target, string $native, LanguagePack $learners): bool
    {
        $bare = self::bare($native);
        if ($bare === '' || $bare === self::bare($target)) {
            return true;
        }
        if (! $learners->has('script_letters')) {
            return false;
        }
        preg_match_all('/\p{L}/u', $native, $found);
        $letters = $found[0];
        if ($letters === []) {
            return false;
        }
        $pattern = $learners->pattern('script_letters');
        $own = count(array_filter($letters, static fn (string $letter): bool => preg_match($pattern, $letter) === 1));

        return $own * 2 < count($letters);
    }

    /** A line without its case, marks and spaces — what «the same words» is read on. */
    private static function bare(string $line): string
    {
        return mb_strtolower((string) preg_replace('/[\p{P}\p{S}\s]+/u', '', $line));
    }
}
