<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/**
 * The checks a validation could not run for want of a language pack — one entry per code and side, whatever
 * number of times the rules asked. What a lesson needs of its languages does not depend on what the lesson says,
 * so every validation with the same pair skips the same checks.
 */
final class PackSkips
{
    /** @var array<string, PackSkip> «code|side» → the skip */
    private array $skips = [];

    /** @param list<string> $keys */
    public function record(string $code, LanguageSide $side, string $language, array $keys): void
    {
        $at = $code.'|'.$side->value;
        $known = $this->skips[$at]->keys ?? [];
        $this->skips[$at] = new PackSkip($code, $side, $language, array_values(array_unique([...$known, ...$keys])));
    }

    /** @return list<PackSkip> in the order the checks were skipped */
    public function all(): array
    {
        return array_values($this->skips);
    }

    /** @return list<string> the codes skipped, each once */
    public function codes(): array
    {
        return array_values(array_unique(array_map(static fn (PackSkip $s): string => $s->code, $this->skips)));
    }
}
