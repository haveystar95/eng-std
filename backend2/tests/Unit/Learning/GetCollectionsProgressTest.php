<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetCollectionsProgress;
use App\Modules\Learning\Application\Query\GetCollectionsProgressHandler;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\InMemoryProgressSnapshotReader;
use Tests\Doubles\InMemoryUserCollectionTermsReader;

it('derives learned, mastered and due per collection', function () {
    $now = new DateTimeImmutable('2026-07-29T08:00:00Z');
    $mastered = TermId::generate()->value; // review, interval 30, overdue
    $learned = TermId::generate()->value;  // review, interval 5, not due yet
    $fresh = TermId::generate()->value;    // no progress row → not started

    $handler = new GetCollectionsProgressHandler(
        new InMemoryUserCollectionTermsReader([], ['COL' => [$mastered, $learned, $fresh]]),
        new InMemoryProgressSnapshotReader([
            $mastered => new DueTermView(TermId::fromString($mastered), LearningState::Review, 30, $now->modify('-1 day')),
            $learned => new DueTermView(TermId::fromString($learned), LearningState::Review, 5, $now->modify('+1 day')),
        ]),
    );

    $result = $handler(new GetCollectionsProgress(UserId::generate(), $now));

    expect($result)->toHaveCount(1);
    expect($result[0]->collectionId)->toBe('COL');
    expect($result[0]->total)->toBe(3);
    expect($result[0]->learned)->toBe(2);   // both review-state terms
    expect($result[0]->mastered)->toBe(1);  // only the interval >= 21 one
    expect($result[0]->due)->toBe(1);       // only the overdue one
});

it('returns nothing when the user has no collections', function () {
    $handler = new GetCollectionsProgressHandler(
        new InMemoryUserCollectionTermsReader(),
        new InMemoryProgressSnapshotReader(),
    );

    expect($handler(new GetCollectionsProgress(UserId::generate(), new DateTimeImmutable())))->toBe([]);
});
