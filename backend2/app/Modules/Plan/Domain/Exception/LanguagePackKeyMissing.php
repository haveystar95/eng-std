<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Exception;

use LogicException;

/**
 * A rule read a key of a language pack without asking whether the pack has it. Not a state of the data — a
 * missing key is normal, and the rule that needs it does not run — but a rule that forgot to ask.
 */
final class LanguagePackKeyMissing extends LogicException
{
    public static function of(string $language, string $key): self
    {
        return new self("Language pack «{$language}» has no «{$key}»: the rule must ask the context before it reads.");
    }
}
