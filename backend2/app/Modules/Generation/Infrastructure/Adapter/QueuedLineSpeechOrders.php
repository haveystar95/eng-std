<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesLineSpeech;
use App\Modules\Learning\Application\Port\OrdersLineSpeech;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Заказ озвучки из ПОСАДКИ, поверх того же диспетчера, что и заказ из генерации дня.
 *
 * Тот же диспетчер, а не второй путь: тумблер трубы, очередь и джоба обязаны быть одни. Разница
 * ровно в том, КТО заметил недостачу — генератор дня в момент, когда день написан, или посадка в
 * момент, когда человек этот день открыл ({@see OrdersLineSpeech}).
 */
final readonly class QueuedLineSpeechOrders implements OrdersLineSpeech
{
    public function __construct(private DispatchesLineSpeech $dispatcher) {}

    public function order(array $collectionIds): void
    {
        $seen = [];
        foreach ($collectionIds as $collectionId) {
            if (isset($seen[$collectionId->value])) {
                continue;
            }
            $seen[$collectionId->value] = true;
            $this->dispatcher->dispatch(CollectionId::fromString($collectionId->value));
        }
    }
}
