<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddTermToCollection;
use App\Modules\Collections\Application\Command\AddTermToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Collections\Domain\Exception\CollectionNotFound;
use App\Modules\Collections\Domain\Exception\NotCollectionOwner;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryCollectionRepository;

beforeEach(function () {
    $this->repo = new InMemoryCollectionRepository();
    $this->clock = new FixedClock(new DateTimeImmutable('2026-07-27T00:00:00Z'));
    $this->owner = UserId::generate();
});

function createCollection(object $ctx): CollectionId
{
    $handler = new CreateCustomCollectionHandler($ctx->repo, $ctx->clock);

    return $handler(new CreateCustomCollection(
        $ctx->owner, 'Travel', new LanguageCode('ru'), new LanguageCode('en'),
    ));
}

it('creates a custom collection and persists it', function () {
    $id = createCollection($this);

    expect($this->repo->count())->toBe(1)
        ->and($this->repo->findById($id))->not->toBeNull();
});

it('adds a term to a collection owned by the actor', function () {
    $id = createCollection($this);
    $term = TermId::generate();

    (new AddTermToCollectionHandler($this->repo))(
        new AddTermToCollection($id, $term, $this->owner),
    );

    expect($this->repo->findById($id)?->itemsCount())->toBe(1);
});

it('rejects adding a term by a non-owner', function () {
    $id = createCollection($this);

    expect(fn () => (new AddTermToCollectionHandler($this->repo))(
        new AddTermToCollection($id, TermId::generate(), UserId::generate()),
    ))->toThrow(NotCollectionOwner::class);
});

it('fails when the collection does not exist', function () {
    expect(fn () => (new AddTermToCollectionHandler($this->repo))(
        new AddTermToCollection(CollectionId::generate(), TermId::generate(), $this->owner),
    ))->toThrow(CollectionNotFound::class);
});
