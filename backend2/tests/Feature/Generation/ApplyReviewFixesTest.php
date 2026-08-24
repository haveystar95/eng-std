<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\ApplyEnrichmentReview;
use App\Modules\Generation\Application\Command\ApplyEnrichmentReviewHandler;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const FIX_EXAMPLE = 'Do you offer online banking services?';

/** A term with one pinned example, one distractor whose label is wrong, and one noted variant. */
function seedFixTarget(): string
{
    $termId = Ulid::generate();
    $exampleId = Ulid::generate();

    DB::table('terms')->insert([
        'id' => $termId, 'text' => 'online banking', 'normalized_text' => 'online banking',
        'lang' => 'en', 'type' => 'phrase', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now()->subDay(),
    ]);
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => FIX_EXAMPLE,
        'translation' => 'Вы предлагаете онлайн-банкинг?', 'source' => 'ai',
    ]);
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId,
        'sentence' => 'Do you offer online banking at services?',
        'error_type' => 'preposition', 'error_span' => 'at', 'correction' => 'services',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_accepted_variants')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'text' => 'internet banking',
        'note' => 'Синонимы."},{', 'generator_version' => 'enrich-v1',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

it('rewrites a span and correction the reviewer fixed', function () {
    $termId = seedFixTarget();

    $outcome = app(ApplyEnrichmentReviewHandler::class)(new ApplyEnrichmentReview([
        'fix_distractors' => [[
            'term' => 'online banking',
            'sentence' => 'Do you offer online banking at services?',
            'error_span' => 'at services',
            'correction' => 'services',
        ]],
    ], 'enrich-v1'));

    $row = DB::table('example_distractors')->where('sentence', 'Do you offer online banking at services?')->first();

    expect($outcome->distractorsFixed)->toBe(1)
        ->and($outcome->unmatched)->toBe([])
        ->and($row?->error_span)->toBe('at services')
        ->and($row?->correction)->toBe('services')
        // The sentence itself is never touched: the reviewer judged the EXPLANATION, not the row.
        ->and($row?->sentence)->toBe('Do you offer online banking at services?');
});

it('refuses a fix whose repair does not give the example back, and says so', function () {
    // The reviewer is better than the model at seeing that the span is wrong and no better at typing
    // the replacement. A fix that leaves the loop open would be scrapped by the next validation pass,
    // so it is reported instead of written.
    seedFixTarget();

    $outcome = app(ApplyEnrichmentReviewHandler::class)(new ApplyEnrichmentReview([
        'fix_distractors' => [[
            'term' => 'online banking',
            'sentence' => 'Do you offer online banking at services?',
            'error_span' => 'at',
            'correction' => 'at the',
        ]],
    ], 'enrich-v1'));

    $row = DB::table('example_distractors')->where('sentence', 'Do you offer online banking at services?')->first();

    expect($outcome->distractorsFixed)->toBe(0)
        ->and($outcome->unmatched)->toHaveCount(1)
        ->and($outcome->unmatched[0])->toContain('не даёт эталон')
        ->and($row?->error_span)->toBe('at');
});

it('cleans a variant note and keeps the variant', function () {
    seedFixTarget();

    $outcome = app(ApplyEnrichmentReviewHandler::class)(new ApplyEnrichmentReview([
        'set_variant_notes' => [[
            'term' => 'online banking', 'text' => 'internet banking', 'note' => 'Синонимы.',
        ]],
    ], 'enrich-v1'));

    expect($outcome->variantNotesFixed)->toBe(1)
        ->and(DB::table('term_accepted_variants')->where('text', 'internet banking')->value('note'))->toBe('Синонимы.')
        ->and(DB::table('term_accepted_variants')->where('text', 'internet banking')->exists())->toBeTrue();
});

it('touches the term so the fix reaches an already-synced device', function () {
    // The delta feed decides by terms.updated_at. Without the touch, a phone that already mirrored the
    // term would keep showing the underline under the wrong word forever.
    $termId = seedFixTarget();
    $before = DB::table('terms')->where('id', $termId)->value('updated_at');

    app(ApplyEnrichmentReviewHandler::class)(new ApplyEnrichmentReview([
        'fix_distractors' => [[
            'term' => 'online banking',
            'sentence' => 'Do you offer online banking at services?',
            'error_span' => 'at services',
            'correction' => 'services',
        ]],
    ], 'enrich-v1'));

    expect(DB::table('terms')->where('id', $termId)->value('updated_at'))->not->toBe($before);
});

it('reports a fix that matched no row instead of counting it as done', function () {
    seedFixTarget();

    $outcome = app(ApplyEnrichmentReviewHandler::class)(new ApplyEnrichmentReview([
        'fix_distractors' => [[
            'term' => 'online banking',
            'sentence' => 'A sentence that is not in the database.',
            'error_span' => 'not',
            'correction' => 'never',
        ]],
    ], 'enrich-v1'));

    expect($outcome->distractorsFixed)->toBe(0)
        ->and($outcome->unmatched)->toHaveCount(1);
});
