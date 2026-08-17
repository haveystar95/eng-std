<?php

declare(strict_types=1);

namespace App\Modules\Collections\Presentation\Console;

use App\Modules\Collections\Application\Command\PublishCollectionToStore;
use App\Modules\Collections\Application\Command\PublishCollectionToStoreHandler;
use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Console\Command;

/**
 * Promote a collection to the curated store: ownerless, system-typed, public, source=curated,
 * optionally premium. The collection then shows up in `GET /store/collections` for its language
 * pair. Used by Session B to publish starter content generated with `generation:make`. Idempotent.
 */
final class StorePublishCommand extends Command
{
    protected $signature = 'store:publish
        {collection : collection id (ULID) to publish}
        {--premium : put the collection behind the subscription gate}';

    protected $description = 'Publish an existing collection to the curated store (ownerless, public)';

    public function handle(PublishCollectionToStoreHandler $publish): int
    {
        $id = $this->asString($this->argument('collection'));
        if (! Ulid::isValid($id)) {
            $this->error("Not a valid collection id: {$id}");

            return self::FAILURE;
        }

        $premium = (bool) $this->option('premium');

        try {
            $publish(new PublishCollectionToStore(CollectionId::fromString($id), $premium));
        } catch (CollectionNotFound $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Published {$id} to the store" . ($premium ? ' (premium).' : '.'));

        return self::SUCCESS;
    }

    private function asString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
