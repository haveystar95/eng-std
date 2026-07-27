<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Query\GetDueTerms;
use App\Modules\Learning\Application\Query\GetDueTermsHandler;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\InMemoryDueTermsReader;

/**
 * @param int $count
 * @return list<DueTermView>
 */
function dueViews(int $count, LearningState $state): array
{
    $views = [];
    for ($i = 0; $i < $count; $i++) {
        $views[] = new DueTermView(TermId::generate(), $state, 4, null);
    }

    return $views;
}

beforeEach(function () {
    $this->user = UserId::generate();
    $this->now = new DateTimeImmutable('2026-07-27T08:00:00Z');
});

it('fills leftover session slots with new terms, due first', function () {
    $reader = new InMemoryDueTermsReader(
        dueTerms: dueViews(5, LearningState::Review),
        newTerms: dueViews(10, LearningState::New),
    );

    $result = (new GetDueTermsHandler($reader))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10),
    );

    expect($result)->toHaveCount(15);
    expect($result[0]->state)->toBe(LearningState::Review);
});

it('shows no new terms when due cards already fill the session', function () {
    $reader = new InMemoryDueTermsReader(
        dueTerms: dueViews(25, LearningState::Review),
        newTerms: dueViews(10, LearningState::New),
    );

    $result = (new GetDueTermsHandler($reader))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 10),
    );

    expect($result)->toHaveCount(20)
        ->and(array_filter($result, fn (DueTermView $v): bool => $v->state === LearningState::New))->toBe([]);
});

it('never exceeds the remaining daily new-term quota', function () {
    $reader = new InMemoryDueTermsReader(
        dueTerms: dueViews(5, LearningState::Review),
        newTerms: dueViews(10, LearningState::New),
    );

    $result = (new GetDueTermsHandler($reader))(
        new GetDueTerms($this->user, $this->now, sessionSize: 20, newTermsRemaining: 3),
    );

    expect($result)->toHaveCount(8); // 5 due + 3 new
});

it('caps the session size at 100', function () {
    $reader = new InMemoryDueTermsReader(dueTerms: dueViews(150, LearningState::Review));

    $result = (new GetDueTermsHandler($reader))(
        new GetDueTerms($this->user, $this->now, sessionSize: 500),
    );

    expect($result)->toHaveCount(100)
        ->and($reader->dueLimits[0])->toBe(100);
});
