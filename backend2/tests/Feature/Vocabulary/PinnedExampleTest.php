<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A term may end up with several examples (ImportTerm appends one per generation pass — 50 of the
 * 766 terms in the live database carry 2–3). The card shows exactly one, so which one has to be
 * PINNED: the reader orders by `id`, which is a ULID, so a term keeps its FIRST example for good.
 *
 * The ids below are deliberately inserted in REVERSE order, so a reader without an explicit order
 * returns the wrong row on the very first read rather than only after the table has been churned.
 */
function seedExamplesOutOfOrder(string $termId): void
{
    DB::table('terms')->insert([
        'id' => $termId,
        'lang' => 'en',
        'text' => 'withdraw cash',
        'normalized_text' => 'withdraw cash',
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (['C', 'B', 'A'] as $letter) {
        seedExample([
            'id' => str_pad("01EX{$letter}", 26, '0'),
            'term_id' => $termId,
            'sentence' => "Example {$letter}.",
            'translation' => "Пример {$letter}.",
            'source' => 'ai',
        ]);
    }
}

it('pins one example per term, whatever the physical row order', function () {
    $termId = str_pad('01TERM', 26, '0');
    seedExamplesOutOfOrder($termId);

    $read = fn (): ?string => app(TermContentReader::class)
        ->byIds([TermId::fromString($termId)], 'ru')[$termId]->example;

    // Lowest id wins — not the row that happened to be inserted first.
    expect($read())->toBe('Example A.')
        ->and($read())->toBe('Example A.');

    // An UPDATE rewrites the tuple at the end of the page, so an unordered sequential scan starts
    // handing back a DIFFERENT example from here on. This is the case that made the bug invisible
    // in tests and visible in the app.
    DB::table('term_examples')
        ->where('id', str_pad('01EXA', 26, '0'))
        ->update(['source' => 'user']);

    expect($read())->toBe('Example A.');
});

it('serves the same pinned example to the client through /sync', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');

    foreach (['C', 'B', 'A'] as $letter) {
        seedExample([
            'id' => str_pad("01EX{$letter}", 26, '0'),
            'term_id' => $termId,
            'sentence' => "Example {$letter}.",
            'translation' => "Пример {$letter}.",
            'source' => 'ai',
        ]);
    }

    $delta = sync($this, $token);
    $synced = collect($delta['changes']['terms'])->firstWhere('id', $termId);

    // The client mirrors ONE example per term. It must be the one the server also puts on the card
    // (both go through TermContentReader) — otherwise anything keyed to the example, starting with
    // the distractors of `pick_correct`, would line up against a sentence the user never sees.
    $onCard = app(TermContentReader::class)->byIds([TermId::fromString($termId)], 'ru')[$termId];

    expect($synced['example'])->toBe('Example A.')
        ->and($synced['example'])->toBe($onCard->example)
        ->and($synced['example_translation'])->toBe($onCard->exampleTranslation);
});
