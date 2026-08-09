<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** A minimal reference to a collection a term belongs to. */
final readonly class CollectionRefRow
{
    public function __construct(
        public string $id,
        public string $title,
        public string $type,
    ) {}
}
