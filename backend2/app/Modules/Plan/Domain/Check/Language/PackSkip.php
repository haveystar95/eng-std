<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/**
 * A CHECK THAT DID NOT RUN FOR WANT OF A LANGUAGE PACK (наряд GEN-2b): the code, the side of the pair it reads,
 * the language, and the keys that language's pack does not have. Never a finding — the lesson is not wrong, the
 * validator does not know the language — and counted as `lang.pack_missing`.
 */
final readonly class PackSkip
{
    /** @param list<string> $keys */
    public function __construct(
        public string $code,
        public LanguageSide $side,
        public string $language,
        public array $keys,
    ) {}

    /** @return array{code: string, side: string, language: string, keys: list<string>} */
    public function toArray(): array
    {
        return ['code' => $this->code, 'side' => $this->side->value, 'language' => $this->language, 'keys' => $this->keys];
    }
}
