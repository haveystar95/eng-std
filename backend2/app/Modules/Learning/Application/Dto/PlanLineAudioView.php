<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * Одна готовая озвучка на пейлоаде посадки: чья реплика, что в ней сказано, и по какому адресу
 * лежит файл.
 *
 * ТЕКСТ здесь не украшение и не дубль. Голос в приложении вызывается текстом, а не карточкой
 * («скажи эту строку»), поэтому пара «текст → файл» — это ровно то, что клиент кладёт в кэш; term
 * id рядом для того, чтобы «эта реплика» оставалась опознаваемой в логах и в тестах.
 */
final readonly class PlanLineAudioView
{
    public function __construct(
        public string $termId,
        public string $text,
        public string $audioId,
    ) {}
}
