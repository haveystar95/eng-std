<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Language;

/**
 * Every language pack the deployment has, by code (`config/lesson/lang/*.php`). A code with no pack is a pack
 * with nothing in it — {@see LanguagePack::none()} — not an error: a lesson in that language is checked by the
 * rules that need no language, and the rest count as missing.
 *
 * Every pack handed out — a code with no pack too — knows its NEIGHBOURS (наряд LANG-1 §5): each other pack's letters
 * and most frequent words ({@see LanguagePack::asNeighbour()}), so a rule holding one pack can tell that language from
 * the others written in the same letters ({@see \App\Modules\Plan\Domain\Service\ReplyNative}).
 */
final readonly class LanguagePacks
{
    /** @var array<string, LanguagePack> */
    private array $packs;

    /** @var array<string, array{script_letters: ?string, common_words: list<string>}> every pack as its neighbours see it */
    private array $neighbours;

    /** @param array<array-key, mixed> $packs code → the pack's keys, as the config holds them */
    public function __construct(array $packs)
    {
        $written = [];
        foreach ($packs as $code => $data) {
            $code = strtolower(trim((string) $code));
            if ($code === '' || ! is_array($data)) {
                continue;
            }
            /** @var array<string, mixed> $data */
            $written[$code] = $data;
        }
        $neighbours = [];
        foreach ($written as $code => $data) {
            $neighbours[$code] = (new LanguagePack($code, $data))->asNeighbour();
        }
        $out = [];
        foreach ($written as $code => $data) {
            $out[$code] = new LanguagePack($code, $data, $neighbours);
        }
        $this->packs = $out;
        $this->neighbours = $neighbours;
    }

    public function for(string $code): LanguagePack
    {
        $code = strtolower(trim($code));

        return $this->packs[$code] ?? LanguagePack::none($code, $this->neighbours);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->packs);
    }
}
