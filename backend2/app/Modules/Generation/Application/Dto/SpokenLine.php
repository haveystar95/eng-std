<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * Что вендор вернул на одну реплику: байты, формат и — если он назвал — сколько это стоило.
 *
 * `costUsd` строкой, а не float: деньги в этом проекте везде строка с шестью знаками
 * ({@see \App\Modules\Shared\Domain\Service\ModelCost}), и смешивать две записи одной величины —
 * известный способ получить два разных итога на одном экране.
 */
final readonly class SpokenLine
{
    public function __construct(
        public string $bytes,
        public string $format,
        public ?int $durationMs = null,
        public ?string $costUsd = null,
    ) {}
}
