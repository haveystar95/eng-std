<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\Image;

/**
 * THE TONE A PHOTO SLOT IS PAINTED WITH (DAY-UI-2) — before the bytes arrive, and instead of a
 * photo that was not found, so no card of the day is ever an empty grey hole.
 *
 * The first tone known wins, in the order the caller names them: the photo's own, then its scene's
 * (a word belongs to the picture of its scene), then the plan cover's, and last the theme's empty
 * photo slot — the design's `photoPlaceholder`, «подложка пустого слота #E3DCCF».
 */
final class ImageTones
{
    public const THEME = '#E3DCCF';

    public static function first(?string ...$tones): string
    {
        foreach ($tones as $tone) {
            $normal = Image::normalTone($tone);
            if ($normal !== null) {
                return $normal;
            }
        }

        return self::THEME;
    }
}
