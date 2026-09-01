<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanChoiceFloor;

it('lets a four-option level fall to three', function () {
    expect((new PlanChoiceFloor())->forPreferred(4))->toBe(3);
});

it('leaves a three-option level alone — it is already at the floor', function () {
    expect((new PlanChoiceFloor())->forPreferred(3))->toBe(3);
});

it('never asks for more than the level wants', function () {
    // A floor above the ceiling would refuse every card in the level it was meant to help.
    expect((new PlanChoiceFloor(minOptions: 4))->forPreferred(3))->toBe(3);
});

it('never falls below two, whatever the configuration says', function () {
    // One option is not a question — the same rule the ordinary session keeps.
    expect((new PlanChoiceFloor(minOptions: 1))->forPreferred(4))->toBe(2);
});
