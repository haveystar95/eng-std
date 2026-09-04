<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Озвучить реплики коллекции дня — шаг после того, как день уже пригоден (наряд TTS-1, Ч.1.1).
 *
 * Коллекция, а не день: озвучке всё равно, чей это день и какой он по счёту, ей нужен НАБОР
 * КАРТОЧЕК — ровно то, чем коллекция и является. Это и делает шаг переиспользуемым: набор
 * спасателей лежит в коллекции дня 1 и озвучивается тем же вызовом, без второй ветки для него.
 */
final readonly class SpeakCollectionLines
{
    public function __construct(public CollectionId $collectionId) {}
}
