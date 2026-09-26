<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\TextLanguage;

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
 * ONE MORE STEP FOR LANGUAGES THAT SHARE THEIR LETTERS (наряд LANG-1 §5, дополняет DECISIONS п. 419). With Polish, German
 * or Belarusian learners the letters say nothing: a Polish learner's English line is as Latin as their Polish one, a
 * Belarusian's Russian as Cyrillic. The order's rule — «common_words родного < 2 и целевого ≥ 2» — reads the line's
 * words instead: the translation is missing when it holds fewer than two of the words only the learner's language has
 * among its most frequent (`common_words`) and at least two of the words only the other language has. Both counts, so a
 * short native line with one frequent word or none («Tak.», «Dzień dobry!») is still a translation, and so is a line
 * with two of its own beside a quoted sign in the other language. The words both languages use often («i», «to», «a»
 * of Polish and English) count for neither side.
 *
 * The "other language" is not only the target: the guard is called with the learner's pack alone (the target's is not in
 * hand where the talk calls it), and that pack knows its neighbours ({@see LanguagePack::neighbours()}). So the rule runs
 * against EVERY other language of the deployment written in the same letters — the target among them, a superset of the
 * order's letter: a German line under a Polish learner of English is no translation either. A neighbour is a pack whose
 * `script_letters` pattern is the very same string as the learner's; a language in other letters is left to the step
 * before (a Russian line quoting «Ibuprofen» is never compared with English words), and a language whose pack writes no
 * letters or no frequent words is compared with nobody.
 *
 * The words of the line are its DISTINCT runs of letters, lower-cased as {@see LanguagePack::normal()} gives them — split
 * on everything else, the apostrophe too, so an elision gives up its word («l'hôpital» → «l», «hôpital»; «j'ai» → «j»,
 * «ai»). A word said three times counts once: «The doctor, the doctor!» is one English word, not three. The letters and
 * the words are read by {@see TextLanguage} — the one reading the definition of a word is checked by too (наряд LANG-1b
 * §4); the thresholds here are this guard's own.
 *
 * What it cannot see is a translation that is Russian and wrong — «Please tell me his temperature» said back as «какая у
 * него самая высокая температура»: that one is the prompt's (v3.3 on, OUTPUT), not the code's.
 */
final class ReplyNative
{
    /** The REDO reason the role is asked again with, and the rejection's reason in the journal. */
    public const REDO = 'native_missing';

    /** Counted on the prompt's version: an answer asked again for its translation. */
    public const CODE = 'conversation.native_missing';

    /** Counted when the second answer has none either: its line is said with no translation (`reply_native` = ""). */
    public const CODE_BLANKED = 'conversation.native_missing_blanked';

    /** How many of a language's own frequent words make a line that language's — and how many of a neighbour's make it theirs. */
    private const WORDS_THAT_TELL = 2;

    public static function missing(string $target, string $native, LanguagePack $learners): bool
    {
        $bare = self::bare($native);
        if ($bare === '' || $bare === self::bare($target)) {
            return true;
        }
        $outside = TextLanguage::outOfScript($native, $learners);
        if ($outside !== false) {
            return $outside === true;
        }

        return self::inNeighboursWords($native, $learners);
    }

    /**
     * Is the line, written in the learner's own letters, a line of ANOTHER language in those letters (наряд LANG-1 §5)?
     * Only the words that tell the two apart count: those both languages use often («i», «to», «a» of Polish and English)
     * say nothing either way.
     */
    private static function inNeighboursWords(string $native, LanguagePack $learners): bool
    {
        foreach (TextLanguage::tellingWords($native, $learners) as $row) {
            if ($row['mine'] < self::WORDS_THAT_TELL && $row['theirs'] >= self::WORDS_THAT_TELL) {
                return true;
            }
        }

        return false;
    }

    /** A line without its case, marks and spaces — what «the same words» is read on. */
    private static function bare(string $line): string
    {
        return mb_strtolower((string) preg_replace('/[\p{P}\p{S}\s]+/u', '', $line));
    }
}
