<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The bytes of a sized scene photo and the digest that names them (the ETag). */
final readonly class SceneImageFile
{
    public function __construct(
        public string $bytes,
        public string $digest,
    ) {}
}
