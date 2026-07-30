<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('picks other target texts as distractors and drops translation near-duplicates', function () {
    [$user] = learner();
    // "withdraw money" shares the translation "снять наличные" with the target — it would read
    // as correct for the same prompt, so it must not become a distractor.
    [$col, $withdrawCash] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    addWordTo($col, $user->id, 'withdraw money', 'снять наличные');
    $deposit = addWordTo($col, $user->id, 'deposit', 'депозит');
    $bank = addWordTo($col, $user->id, 'bank', 'банк');

    $distractors = app(DistractorReader::class)->forTarget(
        TermId::fromString($withdrawCash),
        [$withdrawCash, $deposit, $bank],
        3,
    );

    expect($distractors)->not->toContain('withdraw cash')  // never the target itself
        ->and($distractors)->not->toContain('withdraw money') // near-duplicate excluded
        ->and($distractors)->toContain('deposit')
        ->and($distractors)->toContain('bank');
});
