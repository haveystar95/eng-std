<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\SpeakCollectionLines;
use App\Modules\Generation\Application\Command\SpeakCollectionLinesHandler;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Озвучивает реплики коллекции ПОСЛЕ того, как день записан, — отдельной джобой, чтобы не иметь
 * возможности задержать или уронить генерацию (наряд TTS-1, Ч.1.2). День остаётся `ready` при любом
 * исходе; исчерпав повторы, джоба оставляет реплики без файлов, и они звучат системным голосом.
 *
 * Повторов больше, чем у картинок, и пауза длиннее: у бесплатного тарифа одного из кандидатов
 * лимит — десять запросов в минуту на модель (пойман живьём в Ч.0.3), а сцена — это пять реплик
 * плюс пять спасателей. Дожидаться окна дешевле, чем не озвучить сцену.
 */
final class SpeakLinesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(private readonly string $collectionId) {}

    public function handle(SpeakCollectionLinesHandler $handler, OutboundCallContext $context): void
    {
        // Каждый вызов вендора помечен коллекцией, ради которой он куплен, — «во что обошлась
        // озвучка этого дня» становится одним фильтром в логе, а не догадкой.
        $context->run(null, $this->collectionId, fn () => $handler(
            new SpeakCollectionLines(CollectionId::fromString($this->collectionId)),
        ));
    }

    public function failed(Throwable $e): void
    {
        Log::warning('SpeakLinesJob failed; lines keep the system voice', [
            'collection_id' => $this->collectionId,
            'error' => $e->getMessage(),
        ]);
    }
}
