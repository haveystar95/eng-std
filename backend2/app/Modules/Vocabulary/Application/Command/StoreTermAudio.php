<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Положить озвучку реплики: байты на диск, строку в базу. Кросс-модульный вход — станок озвучки
 * (Generation) покупает звук и приносит его сюда.
 *
 * Идемпотентна и НИКОГДА не перезаписывает: файл этого голоса и этого темпа у термина уже есть →
 * команда молча ничего не делает. Кэш общий на всех пользователей именно из-за этого — второй план,
 * попросивший ту же фразу, получает готовый файл, а не второй счёт.
 */
final readonly class StoreTermAudio
{
    public function __construct(
        public TermId $termId,
        public LineVoice $voice,
        public string $format,
        public string $bytes,
        public ?int $durationMs = null,
        public ?string $costUsd = null,
    ) {}
}
