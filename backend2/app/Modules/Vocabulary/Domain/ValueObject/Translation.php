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

    /**
     * The same line, no longer the term's primary reading.
     *
     * A demotion is not a judgement about the text and it is never a deletion: an older reading of a
     * term stays a legitimate alternative («cash register» → «касса» beside «кассовый аппарат»), it
     * simply stops competing to be the QUESTION on the card. Its provenance travels with it, because
     * the row is still the row that prompt wrote — see {@see Term::addTranslation()} for the rule.
     */
    public function demoted(): self
    {
        return $this->isPrimary
            ? new self($this->lang, $this->text, false, $this->provenance)
            : $this;
    }
}
