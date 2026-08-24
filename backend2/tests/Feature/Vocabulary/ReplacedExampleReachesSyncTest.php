<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ReplaceTermExample;
use App\Modules\Vocabulary\Application\Command\ReplaceTermExampleHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-19: a replaced example has to REACH the phone.
 *
 * The delta feed picks terms by `terms.updated_at`, and an example lives in `term_examples`. So a
 * replace wrote the new sentence and moved no timestamp the feed looks at: the term was already
 * mirrored, its row never re-entered a delta, and the device went on showing the old example
 * forever. Both callers of this command exist to FIX a bad example — «Новый пример» by hand, and
 * the echo repair automatically — so the fix reached everything except the device that had the
 * complaint.
 *
 * The test is written against the DELTA rather than against the timestamp, because the timestamp
 * is only the mechanism; what was broken was the promise that a change ships.
 */
it('puts the term back in the sync delta when its example is replaced', function () {
    [$user, $token] = learner();
    [$col, $termId] = seedCollectionWith($user, 'overwhelm', 'переполнять');

    seedExample([
        'term_id' => $termId,
        'sentence' => 'The old example sentence.',
        'translation' => 'Старое предложение.',
        'source' => 'user',
    ]);

    // A device that is fully caught up: everything it holds is older than the cursor it will send.
    DB::table('collections')->where('id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('collection_items')->where('collection_id', $col)->update(['updated_at' => now()->subDay()]);
    DB::table('terms')->where('id', $termId)->update(['updated_at' => now()->subDay()]);

    $cursor = sync($this, $token)['server_time'];
    expect(array_column(sync($this, $token, 'since=' . urlencode($cursor))['changes']['terms'], 'id'))
        ->not->toContain($termId, 'precondition: the device is caught up');

    app(ReplaceTermExampleHandler::class)(new ReplaceTermExample(
        TermId::fromString($termId),
        'The paperwork threatened to overwhelm her.',
        'Бумажная работа грозила её захлестнуть.',
    ));

    $data = sync($this, $token, 'since=' . urlencode($cursor));
    $term = collect($data['changes']['terms'])->firstWhere('id', $termId);

    expect($term)->not->toBeNull('the replaced example never reached the phone')
        ->and($term['example'])->toBe('The paperwork threatened to overwhelm her.')
        ->and($term['example_translation'])->toBe('Бумажная работа грозила её захлестнуть.');
});

it('leaves the rest of the term alone — only the timestamp moves', function () {
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'overwhelm', 'переполнять');

    // Timestamps are second-precision, so age the row deliberately rather than racing the clock.
    DB::table('terms')->where('id', $termId)->update(['updated_at' => now()->subDay()]);
    $before = DB::table('terms')->where('id', $termId)->first();

    app(ReplaceTermExampleHandler::class)(new ReplaceTermExample(
        TermId::fromString($termId), 'A sentence.', 'Предложение.',
    ));

    $after = DB::table('terms')->where('id', $termId)->first();

    // A touch, not an edit: the delta needs the row to move, and nothing about the term itself has.
    expect($after->text)->toBe($before->text)
        ->and($after->normalized_text)->toBe($before->normalized_text)
        ->and($after->deleted_at)->toBe($before->deleted_at)
        ->and($after->updated_at)->not->toBe($before->updated_at);
});
