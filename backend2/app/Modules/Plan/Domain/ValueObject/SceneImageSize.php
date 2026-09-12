<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/**
 * The square copies of a scene photo the server keeps: 112 px for the 56-pt circle at 2x, 448 px
 * for 4x and anything larger. Two sizes, not a resize-on-request — the container has no image
 * library and the vendor's CDN crops.
 */
enum SceneImageSize: int
{
    case Small = 112;
    case Large = 448;
}
