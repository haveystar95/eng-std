<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use InvalidArgumentException;

final class Translation
{
    public readonly string $text;

    public function __construct(
        public readonly LanguageCode $lang,
        string $text,
        public readonly bool $isPrimary = false,
    ) {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Translation text cannot be empty.');
        }
        $this->text = $trimmed;
    }
}
