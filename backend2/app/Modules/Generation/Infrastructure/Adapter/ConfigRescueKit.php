<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\RescueKitSource;
use App\Modules\Generation\Domain\ValueObject\RescuePhrase;

/**
 * The rescue kit, read out of `config/generation.php` — «конфиг языкового пакета» of канон §5.
 *
 * Keyed pair-first (`en` → `ru` → five phrases), because the phrase is one thing and its KEY is
 * another: «Could you repeat that, please?» is the same card for every learner of English, and the
 * Russian under it is the half that depends on who is learning. A pair the pack does not know
 * answers empty, and the plan is built without a kit rather than refused.
 */
final readonly class ConfigRescueKit implements RescueKitSource
{
    /** @param array<string, mixed> $pack `config('generation.plan.rescue_kit')` */
    public function __construct(private array $pack) {}

    public function forPair(string $targetLang, string $supportLang): array
    {
        $target = $this->pack[$this->key($targetLang)] ?? null;
        if (! is_array($target)) {
            return [];
        }

        $rows = $target[$this->key($supportLang)] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            /** @var array<string, mixed> $row */
            $phrase = RescuePhrase::fromArray($row);
            if ($phrase !== null) {
                $out[] = $phrase;
            }
        }

        return $out;
    }

    private function key(string $lang): string
    {
        return mb_strtolower(mb_substr(trim($lang), 0, 2));
    }
}
