<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/**
 * Every language pack the deployment has, by code (`config/lesson/lang/*.php`). A code with no pack is a pack
 * with nothing in it — {@see LanguagePack::none()} — not an error: a lesson in that language is checked by the
 * rules that need no language, and the rest count as missing.
 */
final readonly class LanguagePacks
{
    /** @var array<string, LanguagePack> */
    private array $packs;

    /** @param array<array-key, mixed> $packs code → the pack's keys, as the config holds them */
    public function __construct(array $packs)
    {
        $out = [];
        foreach ($packs as $code => $data) {
            $code = strtolower(trim((string) $code));
            if ($code === '' || ! is_array($data)) {
                continue;
            }
            /** @var array<string, mixed> $data */
            $out[$code] = new LanguagePack($code, $data);
        }
        $this->packs = $out;
    }

    public function for(string $code): LanguagePack
    {
        $code = strtolower(trim($code));

        return $this->packs[$code] ?? LanguagePack::none($code);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->packs);
    }
}
