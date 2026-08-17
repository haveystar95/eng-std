<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\FailGeneration;
use App\Modules\Generation\Application\Command\FailGenerationHandler;
use App\Modules\Generation\Application\Command\ProcessGeneration;
use App\Modules\Generation\Application\Command\ProcessGenerationHandler;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;
use App\Modules\Generation\Domain\Repository\GenerationRequestRepository;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the generation off the request thread. The controller returns 202 immediately; this
 * does the model call and the writes. Retries transient failures; once exhausted, failed()
 * records a terminal failure (which also refunds the quota).
 */
final class GenerateCollectionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly string $requestId) {}

    public function handle(
        ProcessGenerationHandler $handler,
        OutboundCallContext $context,
        GenerationRequestRepository $requests,
    ): void {
        $id = GenerationRequestId::fromString($this->requestId);

        try {
            $context->run(null, null, fn () => $handler(new ProcessGeneration($id)));

            // The model call is paid for BEFORE the collection exists, so its log row can only be
            // linked to the collection now, once the request carries the id it produced.
            $collectionId = $requests->findById($id)?->collectionId();
            if ($collectionId !== null) {
                $context->attachCollection($collectionId->value);
            }
        } catch (InvalidGeneratedDraft $e) {
            // A rejected draft is deterministic: the same prompt + model will reject again, so a
            // retry only burns time and money. Fail terminally now — failed() records the reason.
            // Transport/5xx errors are NOT caught here, so they still retry with backoff.
            $this->fail($e);
        }
    }

    public function failed(Throwable $e): void
    {
        app(FailGenerationHandler::class)(new FailGeneration(
            GenerationRequestId::fromString($this->requestId),
            Str::limit($e->getMessage(), 500),
        ));
    }
}
