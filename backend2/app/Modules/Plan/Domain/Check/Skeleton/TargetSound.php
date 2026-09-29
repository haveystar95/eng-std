<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\StageText;
use Normalizer;

/**
 * A READING THAT IS THE TARGET'S OWN SOUND (наряд GEN-4; `lesson_skeleton.v1.1`: «every pronunciation_native is the sound of the
 * TARGET text, never the native text or something close to it»). A word the two languages share — «operator» / «оператор»,
 * «taxi» / «такси», «hotel» / «hotel» — is read close to its native text because it SOUNDS so: that is the sound the prompt
 * asks for, not a translation copied. So a reading is set beside the target's own spelling in the reading's letters — a
 * Cyrillic reading beside the target spelt letter by letter in Cyrillic ({@see spelt()}: the target language's letter
 * groups, then one letter each — «experiență» → «експериенцэ», «Schule» → «шуле»), a Latin one beside the target without
 * its accents — and it is the target's sound when it is at least as close to that spelling as to the native text (or
 * close to it by {@see SOUND}). Letters only, no meaning: a target whose spelling is far from its sound — English
 * «computer» — keeps its cognates' findings.
 */
final class TargetSound
{
    /** Close enough to the target's spelling to be its sound, whatever the native text. */
    public const SOUND = 0.8;

    /** One Latin letter as a Cyrillic reader would write it, where the target language does not say otherwise. */
    private const LETTERS = [
        'a' => 'а', 'á' => 'а', 'à' => 'а', 'â' => 'а', 'b' => 'б', 'c' => 'к', 'ç' => 'с', 'd' => 'д', 'e' => 'е', 'é' => 'е',
        'è' => 'е', 'ê' => 'е', 'ë' => 'е', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'i' => 'и', 'í' => 'и', 'ì' => 'и', 'ï' => 'и',
        'j' => 'ж', 'k' => 'к', 'l' => 'л', 'm' => 'м', 'n' => 'н', 'ñ' => 'нь', 'o' => 'о', 'ó' => 'о', 'ò' => 'о', 'ô' => 'о',
        'ö' => 'ё', 'p' => 'п', 'q' => 'к', 'r' => 'р', 's' => 'с', 't' => 'т', 'u' => 'у', 'ú' => 'у', 'ù' => 'у', 'û' => 'у',
        'ü' => 'ю', 'v' => 'в', 'w' => 'в', 'x' => 'кс', 'y' => 'и', 'ý' => 'и', 'z' => 'з',
    ];

    /** The letter groups — and the single letters — a target language reads otherwise, longest first where they overlap. */
    private const GROUPS = [
        'ro' => ['che' => 'ке', 'chi' => 'ки', 'ghe' => 'ге', 'ghi' => 'ги', 'ce' => 'че', 'ci' => 'чи', 'ge' => 'дже', 'gi' => 'джи', 'ea' => 'я', 'ș' => 'ш', 'ş' => 'ш', 'ț' => 'ц', 'ţ' => 'ц', 'ă' => 'э', 'â' => 'ы', 'î' => 'ы'],
        'it' => ['gli' => 'льи', 'sce' => 'ше', 'sci' => 'ши', 'che' => 'ке', 'chi' => 'ки', 'ghe' => 'ге', 'ghi' => 'ги', 'gn' => 'нь', 'ce' => 'че', 'ci' => 'чи', 'ge' => 'дже', 'gi' => 'джи', 'h' => ''],
        'es' => ['gue' => 'ге', 'gui' => 'ги', 'ch' => 'ч', 'll' => 'й', 'qu' => 'к', 'ce' => 'се', 'ci' => 'си', 'ge' => 'хе', 'gi' => 'хи', 'j' => 'х', 'h' => '', 'z' => 'с', 'v' => 'б'],
        'fr' => ['eau' => 'о', 'au' => 'о', 'ou' => 'у', 'oi' => 'уа', 'ch' => 'ш', 'qu' => 'к', 'gn' => 'нь', 'ai' => 'э', 'ei' => 'э', 'eu' => 'ё', 'h' => ''],
        'de' => ['tsch' => 'ч', 'sch' => 'ш', 'ch' => 'х', 'ck' => 'к', 'ei' => 'ай', 'ie' => 'и', 'eu' => 'ой', 'äu' => 'ой', 'ä' => 'э', 'ß' => 'сс', 'z' => 'ц', 'v' => 'ф', 'j' => 'й'],
        'pl' => ['szcz' => 'щ', 'cz' => 'ч', 'sz' => 'ш', 'rz' => 'ж', 'ch' => 'х', 'dż' => 'дж', 'dź' => 'дзь', 'ą' => 'он', 'ę' => 'ен', 'ł' => 'в', 'ś' => 'сь', 'ć' => 'ць', 'ń' => 'нь', 'ź' => 'зь', 'ż' => 'ж', 'j' => 'й', 'y' => 'ы', 'ó' => 'у'],
        'en' => ['tion' => 'шн', 'sh' => 'ш', 'ch' => 'ч', 'th' => 'з', 'ph' => 'ф', 'ee' => 'и', 'oo' => 'у', 'ou' => 'ау', 'j' => 'дж', 'w' => 'у'],
    ];

    /**
     * Is `$reading` the target's own sound rather than its native text? It is as close to the target spelt in its letters as
     * to the native text, or close to that spelling by {@see SOUND}.
     */
    public static function is(string $reading, string $target, string $native, string $targetCode): bool
    {
        $sound = StageText::similarity($reading, self::spelt($target, $reading, $targetCode));

        return $sound >= min(StageText::similarity($reading, $native), self::SOUND);
    }

    /** The target in the letters of the reading: Cyrillic letter by letter for a Cyrillic reading, else without its accents. */
    public static function spelt(string $target, string $reading, string $targetCode): string
    {
        $text = mb_strtolower($target);
        if (preg_match('/\p{Cyrillic}/u', $reading) !== 1) {
            $bare = Normalizer::normalize($text, Normalizer::FORM_D);

            return (string) preg_replace('/\p{Mn}+/u', '', is_string($bare) ? $bare : $text);
        }

        $groups = self::GROUPS[$targetCode] ?? [];
        $longest = $groups === [] ? 1 : max(array_map(mb_strlen(...), array_keys($groups)));
        $out = '';
        $length = mb_strlen($text);
        for ($i = 0; $i < $length;) {
            for ($size = min($longest, $length - $i); $size > 0; $size--) {
                $piece = mb_substr($text, $i, $size);
                if (isset($groups[$piece])) {
                    $out .= $groups[$piece];
                    $i += $size;

                    continue 2;
                }
            }
            $letter = mb_substr($text, $i, 1);
            $out .= self::LETTERS[$letter] ?? $letter;
            $i++;
        }

        return $out;
    }
}
