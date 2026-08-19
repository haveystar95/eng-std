<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use InvalidArgumentException;

final class Translation
{
    public readonly string $text;

    /**
     * @param  Provenance|null  $provenance  which prompt version and model wrote this line, when it
     *                                       came from the станок. Null for anything typed by a
     *                                       human or loaded from a row that predates the stamp.
     */
    public function __construct(
        public readonly LanguageCode $lang,
        string $text,
        public readonly bool $isPrimary = false,
        public readonly ?Provenance $provenance = null,
    ) {
        $trimmed = trim($text);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Translation text cannot be empty.');
        }
        $this->text = $trimmed;
    }
}
