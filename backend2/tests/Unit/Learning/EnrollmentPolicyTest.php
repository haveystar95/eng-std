<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Exception\PlanHeldCollection;
use App\Modules\Learning\Domain\Exception\PlanHeldTerm;
use App\Modules\Learning\Domain\Service\EnrollmentPolicy;
use App\Modules\Learning\Domain\ValueObject\EnrollmentSources;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\TermId;

beforeEach(function (): void {
    $this->policy = new EnrollmentPolicy();
    $this->term = TermId::generate();
    $this->planId = '01JZZZZZZZZZZZZZZZZZZZZZZZ';
});

it('lets an ordinary word out of the pool', function () {
    $this->policy->assertMayUnenroll($this->term, EnrollmentSources::manual(), []);
})->throwsNoExceptions();

it('refuses a word an active plan is standing on, and names the plan', function () {
    $sources = EnrollmentSources::manual()->with(EnrollmentSources::forPlan($this->planId));

    try {
        $this->policy->assertMayUnenroll($this->term, $sources, [$this->planId]);
        $this->fail('a plan-held word was let out of the pool');
    } catch (PlanHeldTerm $e) {
        expect($e->problemStatus())->toBe(409)
            ->and($e->problemCode())->toBe('plan_held_term')
            ->and($e->problemMeta()['plan_ids'])->toBe([$this->planId]);
    }
});

it('lets the word go once the plan is no longer holding it', function () {
    $sources = EnrollmentSources::manual()->with(EnrollmentSources::forPlan($this->planId));

    // The plan finished, so it is not in the holding set any more — even though its source is
    // still on the row until the release runs.
    $this->policy->assertMayUnenroll($this->term, $sources, []);
})->throwsNoExceptions();

it('releases only the plan reason and leaves the learner own', function () {
    $sources = EnrollmentSources::manual()->with(EnrollmentSources::forPlan($this->planId));

    $after = $this->policy->release($sources, $this->planId);

    expect($after->sources)->toBe(['manual'])
        ->and($after->has('manual'))->toBeTrue()
        ->and($after->planIds())->toBe([]);
});

it('leaves a pair with no reason at all rather than unenrolling it', function () {
    // A word whose ONLY reason was the plan keeps studying: the learner spent days on it and it
    // has a rung and a schedule. What changes is that they may now remove it.
    $sources = EnrollmentSources::fromArray([EnrollmentSources::forPlan($this->planId)]);

    $after = $this->policy->release($sources, $this->planId);
    // No throw here is the assertion: the pair is free to leave the pool now.
    $this->policy->assertMayUnenroll($this->term, $after, [$this->planId]);

    expect($after->isEmpty())->toBeTrue();
});

it('holds through a pause, because pause means «я вернусь»', function () {
    expect(PlanStatus::Paused->holdsTerms())->toBeTrue()
        ->and(PlanStatus::Active->holdsTerms())->toBeTrue()
        ->and(PlanStatus::Draft->holdsTerms())->toBeFalse()
        ->and(PlanStatus::Completed->holdsTerms())->toBeFalse()
        ->and(PlanStatus::Abandoned->holdsTerms())->toBeFalse();
});

it('refuses to delete a day collection while its plan is running', function () {
    $this->policy->assertMayDeleteCollection('01JCOLLECTIONXXXXXXXXXXXXX', $this->planId);
})->throws(PlanHeldCollection::class);

it('lets an ordinary collection go', function () {
    $this->policy->assertMayDeleteCollection('01JCOLLECTIONXXXXXXXXXXXXX', null);
})->throwsNoExceptions();

it('keeps the source list a set with a stable order', function () {
    $sources = EnrollmentSources::fromArray(['manual', 'manual', 'triage', '', 42]);

    expect($sources->sources)->toBe(['manual', 'triage'])
        ->and($sources->with('manual')->sources)->toBe(['manual', 'triage']);
});

it('reads every plan holding a pair, not just the first', function () {
    $second = '01JYYYYYYYYYYYYYYYYYYYYYYY';
    $sources = EnrollmentSources::fromArray([
        'manual',
        EnrollmentSources::forPlan($this->planId),
        EnrollmentSources::forPlan($second),
    ]);

    expect($sources->planIds())->toBe([$this->planId, $second]);
});
