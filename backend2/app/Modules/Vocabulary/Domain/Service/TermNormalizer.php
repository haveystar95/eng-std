<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\Service;

use App\Modules\Vocabulary\Domain\ValueObject\TermType;

/**
 * Produces the canonical `normalized_text` used as the dedup key.
 * Rules: trim, collapse whitespace, casefold, strip a leading article for phrases.
 */
final class TermNormalizer
{
    public function normalize(string $text, TermType $type): string
    {
        $value = mb_strtolower(trim($text));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        if ($type === TermType::Phrase) {
            $value = (string) preg_replace('/^(the|a|an)\s+/u', '', $value);
        }

        return $value;
    }
}
