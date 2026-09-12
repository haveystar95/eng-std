<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\ValueObject;

/** The alert of a letter: a title and one line — ready to print, never content beyond that. */
final readonly class NotificationText
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}
}
