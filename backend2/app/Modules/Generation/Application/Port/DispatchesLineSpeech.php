<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;

/**
 * Ставит озвучку реплик коллекции в очередь — вне запроса, после того как день уже пригоден
 * (та же форма, что и {@see DispatchesImageAttachment}, и по той же причине: озвучка НЕ ИМЕЕТ
 * ПРАВА уронить или задержать генерацию дня).
 *
 * ЗДЕСЬ ЖЕ ЖИВЁТ ТУМБЛЕР ТРУБЫ (наряд TTS-1, Ч.1.4). Выключено — метод не ставит ничего, и ни один
 * байт не уходит вендору; всё работает ровно как до наряда, системным голосом. Тумблер стоит на
 * ВХОДЕ, а не внутри обработчика, чтобы «выключено» означало «никто никуда не ходил», а не
 * «сходили и передумали».
 */
interface DispatchesLineSpeech
{
    public function dispatch(CollectionId $collectionId): void;
}
