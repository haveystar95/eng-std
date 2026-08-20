<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Infrastructure\Job\EnrichCollectionJob;
use App\Modules\Generation\Infrastructure\Job\RepairEchoExamplesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\Bus;

/**
 * Audit A2. The станок builds a card's wrong-answer options out of the card's example, so it has to
 * run AFTER the example is settled. It used to be dispatched first: two independent jobs, and the
 * queue was free to run them in either order. It ran the станок against terms whose echo example had
 * been refused and not yet regenerated — nothing to build distractors from — and marked them done
 * anyway. Four of the five repaired terms in the store carry zero distractors because of it.
 *
 * A chain rather than two dispatches is what makes the order a fact instead of a hope.
 */
function fireFollowUp(): void
{
    app(DispatchesExampleRepair::class)->repairThenEnrich(
        CollectionId::fromString(Ulid::generate()),
        UserId::fromString(Ulid::generate()),
        BuildTermEnrichmentsHandler::VERSION,
    );
}

it('queues the example repair BEFORE the enrichment, in one chain', function () {
    config(['services.generation.auto_repair_examples' => true, 'services.generation.auto_enrich' => true]);
    Bus::fake();

    fireFollowUp();

    Bus::assertChained([RepairEchoExamplesJob::class, EnrichCollectionJob::class]);
});

it('enriches alone when example repair is switched off — there is then nothing to wait for', function () {
    config(['services.generation.auto_repair_examples' => false, 'services.generation.auto_enrich' => true]);
    Bus::fake();

    fireFollowUp();

    Bus::assertChained([EnrichCollectionJob::class]);
});

it('repairs alone when the станок is switched off', function () {
    config(['services.generation.auto_repair_examples' => true, 'services.generation.auto_enrich' => false]);
    Bus::fake();

    fireFollowUp();

    Bus::assertChained([RepairEchoExamplesJob::class]);
});

it('queues nothing when both switches are off', function () {
    config(['services.generation.auto_repair_examples' => false, 'services.generation.auto_enrich' => false]);
    Bus::fake();

    fireFollowUp();

    Bus::assertNothingDispatched();
});
