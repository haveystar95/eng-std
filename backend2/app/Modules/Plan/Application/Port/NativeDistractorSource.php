<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * The catalogue top-up for a choice among translations — `word_choose` asked `term_to_native` and
 * `word_listen`, at any level (SESSION-1e): translations of OTHER words of the same kind and about
 * the same length, when the day's own words are too few.
 */
interface NativeDistractorSource
{
    /**
     * @param  list<string>  $exclude  translations already on the card
     * @return list<string>
     */
    public function translations(LanguageCode $targetLang, LanguageCode $nativeLang, string $like, array $exclude, int $count): array;
}
