<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Blueprint\PlanTitles;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;

/**
 * WHAT A MISSING PHOTO IS SEARCHED BY, IN ORDER (DAY-UI-2, находка PHONE-RUN-1 №4).
 *
 * The lesson leaves `image_prompt` empty for an abstract word — «in progress», «scope», «sharp» —
 * and the search was never asked at all: on 14.09 69 of the dev base's 232 words and chunks had no
 * photo, and every one of them had no prompt. The ladder asks the model's own description first,
 * then the word by itself without its context, then the scene's theme; a scene — its description,
 * its own title, then the plan's cover description. The first photo found ends it. Blank and
 * repeated queries are skipped: the vendor is never asked the same thing twice.
 *
 * The scene's theme for a word is the scene's TITLE, not its photo description: asked with the
 * description, the vendor's first photo is the scene's own, and the word would repeat the day's
 * plate in the grid right under it.
 */
final class ImageQueries
{
    /** @return list<string> */
    public static function forTerm(PlanTerm $term, PlanScene $scene): array
    {
        return self::distinct([$term->imagePrompt(), $term->textTarget(), $scene->titleTarget()]);
    }

    /** @return list<string> */
    public static function forScene(PlanScene $scene, ?PlanTitles $titles): array
    {
        return self::distinct([$scene->imagePrompt(), $scene->titleTarget(), $titles?->coverImagePrompt]);
    }

    /**
     * @param  list<string|null>  $queries
     * @return list<string>
     */
    private static function distinct(array $queries): array
    {
        $out = [];
        $seen = [];
        foreach ($queries as $query) {
            $text = trim((string) $query);
            $key = mb_strtolower($text);
            if ($text === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $text;
        }

        return $out;
    }
}
