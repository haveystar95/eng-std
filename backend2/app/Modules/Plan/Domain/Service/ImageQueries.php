<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Blueprint\PlanTitles;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\ValueObject\ImageQuery;

/**
 * WHAT A MISSING PHOTO IS SEARCHED BY, IN ORDER (DAY-UI-2, reworked by DAY-UI-3).
 *
 * A word: the model's own description (`image_prompt`) first; when the lesson left it empty — an
 * abstract word — the word TOGETHER WITH the scene's theme («appointment, doctor's office»); the last
 * rung is the theme alone, a later page of its answers for every word, so a day of abstract words does
 * not repeat one picture and never repeats its own plate. NEVER the word alone: asked «marketing»,
 * the vendor's first photo was a supermarket (phone, 14.09) — a word without its situation is a
 * different word.
 *
 * The theme of a scene is the place its photo description names («… in a doctor's office, …» →
 * «doctor's office»), or its title when the description names no place.
 *
 * A scene: its description, then its title, then the plan's cover description. The first photo found
 * ends a ladder; blank and repeated questions are skipped.
 */
final class ImageQueries
{
    /** Words of a theme at most — a vendor asked a sentence answers nothing. */
    private const THEME_WORDS = 4;

    /** @return list<ImageQuery> */
    public static function forTerm(PlanTerm $term, PlanScene $scene): array
    {
        $theme = self::theme($scene);
        $word = trim($term->textTarget());

        return self::distinct([
            new ImageQuery((string) $term->imagePrompt()),
            new ImageQuery($word === '' || $theme === '' ? '' : "{$word}, {$theme}"),
            new ImageQuery($theme, 2 + $term->position()),
        ]);
    }

    /** @return list<ImageQuery> */
    public static function forScene(PlanScene $scene, ?PlanTitles $titles): array
    {
        return self::distinct([
            new ImageQuery($scene->imagePrompt()),
            new ImageQuery($scene->titleTarget()),
            new ImageQuery((string) $titles?->coverImagePrompt),
        ]);
    }

    /** «realistic photo of a medical consultation in a doctor's office, patient…» → «doctor's office». */
    public static function theme(PlanScene $scene): string
    {
        if (preg_match('/\b(?:in|at|inside)\s+(?:a|an|the)\s+([^,.;:]+)/iu', $scene->imagePrompt(), $m) === 1) {
            $words = preg_split('/\s+/u', trim($m[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            // «a small clinic reception desk with a patient» — the place ends where the picture goes on.
            $place = [];
            foreach ($words as $word) {
                if (in_array(mb_strtolower($word), ['with', 'where', 'while', 'during', 'and', 'of', 'for', 'as'], true)) {
                    break;
                }
                $place[] = $word;
            }
            if ($place !== []) {
                return implode(' ', array_slice($place, -self::THEME_WORDS));
            }
        }

        return trim($scene->titleTarget());
    }

    /**
     * @param  list<ImageQuery>  $queries
     * @return list<ImageQuery>
     */
    private static function distinct(array $queries): array
    {
        $out = [];
        $seen = [];
        foreach ($queries as $query) {
            $text = trim($query->text);
            if ($text === '' || isset($seen[$query->key()])) {
                continue;
            }
            $seen[$query->key()] = true;
            $out[] = new ImageQuery($text, $query->page);
        }

        return $out;
    }
}
