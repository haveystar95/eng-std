<?php

declare(strict_types=1);

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Exception\NotCollectionOwner;
use App\Modules\Collections\Domain\ValueObject\CollectionSource;
use App\Modules\Collections\Domain\ValueObject\CollectionType;
use App\Modules\Collections\Domain\ValueObject\Visibility;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

function newCustomCollection(UserId $owner): Collection
{
    return Collection::createCustom(
        id: App\Modules\Shared\Domain\ValueObject\CollectionId::generate(),
        ownerId: $owner,
        title: '  Travel  ',
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
        createdAt: new DateTimeImmutable('2026-07-27'),
    );
}

it('creates a custom collection with sane defaults and a trimmed title', function () {
    $c = newCustomCollection(UserId::generate());

    expect($c->type())->toBe(CollectionType::Custom)
        ->and($c->visibility())->toBe(Visibility::Private)
        ->and($c->source())->toBe(CollectionSource::User)
        ->and($c->title())->toBe('Travel')
        ->and($c->itemsCount())->toBe(0);
});

it('adds terms with incrementing positions and dedups', function () {
    $c = newCustomCollection(UserId::generate());
    $t1 = TermId::generate();
    $t2 = TermId::generate();

    $c->addTerm($t1);
    $c->addTerm($t2);
    $c->addTerm($t1); // duplicate ignored

    expect($c->itemsCount())->toBe(2)
        ->and($c->items()[0]->position)->toBe(1)
        ->and($c->items()[1]->position)->toBe(2);
});

it('lets the owner edit but rejects everyone else', function () {
    $owner = UserId::generate();
    $c = newCustomCollection($owner);

    $c->assertEditableBy($owner); // no throw

    expect(fn () => $c->assertEditableBy(UserId::generate()))
        ->toThrow(NotCollectionOwner::class);
});

it('removes a term', function () {
    $c = newCustomCollection(UserId::generate());
    $t = TermId::generate();
    $c->addTerm($t);
    $c->removeTerm($t);

    expect($c->itemsCount())->toBe(0);
});
