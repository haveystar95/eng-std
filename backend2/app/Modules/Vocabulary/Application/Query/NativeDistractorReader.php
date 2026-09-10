<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * Wrong answers on the NATIVE side: translations of single catalogue words of the target language
 * into the native language, about as long as the word being asked about. The sibling of
 * {@see DistractorReader}, which serves the target side; the same rule holds about whose words
 * may be offered — generated or curated material only, never a word a person typed.
 *
 * The one caller is the learning plan's Beginner choice card («слово → выбери перевод»), which
 * draws its wrong options from the other words of the day and comes here only when the day is
 * too small to have three.
 */
interface NativeDistractorReader
{
    /**
     * @param  string  $like  the translation the options should resemble in length (empty = any)
     * @param  list<string>  $exclude  translations that must not come back
     * @return list<string>  up to $count distinct translations
     */
    public function translations(LanguageCode $targetLang, LanguageCode $nativeLang, string $like, array $exclude, int $count): array;
}
