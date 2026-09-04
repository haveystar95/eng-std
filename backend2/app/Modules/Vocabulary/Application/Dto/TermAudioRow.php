<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * ОДНА ГОТОВАЯ ОЗВУЧКА реплики: чем сказана, где лежит, сколько весит.
 *
 * `id` — не служебный номер, а АДРЕС: клиент качает файл по `/api/v1/audio/lines/{id}.mp3`, и
 * поэтому смена голоса (новая строка → новый id) сама по себе означает новый URL. Кэш телефона
 * ключуется URL-ом, так что «старый кэш не играется за новый голос» держится схемой, а не
 * дисциплиной клиента.
 */
final readonly class TermAudioRow
{
    public function __construct(
        public string $id,
        public string $termId,
        public string $voice,
        public string $variant,
        public string $format,
        public string $path,
        public int $bytes,
        public ?int $durationMs = null,
    ) {}
}
