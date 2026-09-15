<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * Что вендор вернул на одну строку (TTS-2): байты, формат, сколько звучит, и счёт — символы текста, кредиты, которые
 * вендор списал с аккаунта (`character-cost`), их фактическая цена и id его запроса (один id — один оплаченный вызов).
 *
 * `costUsd` строкой, а не float: деньги в этом проекте везде строка с шестью знаками
 * ({@see \App\Modules\Shared\Domain\Service\ModelCost}).
 */
final readonly class SpokenLine
{
    public function __construct(
        public string $bytes,
        public string $format,
        public ?int $durationMs,
        public int $characters,
        public int $credits,
        public string $costUsd,
        public ?string $requestId = null,
    ) {}
}
