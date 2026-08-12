<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\AuditDistractors;
use App\Modules\Generation\Application\Command\AuditDistractorsHandler;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const AUDIT_EXAMPLE = 'I think I have a cold.';

/** @param  list<array{0: string, 1: string, 2: string}>  $distractors  sentence, span, correction */
function seedAuditTarget(array $distractors, string $example = AUDIT_EXAMPLE): string
{
    $termId = Ulid::generate();
    $exampleId = Ulid::generate();

    DB::table('terms')->insert([
        'id' => $termId, 'text' => 'cold', 'normalized_text' => 'cold',
        'lang' => 'en', 'type' => 'word', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_examples')->insert([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => $example,
        'sentence_translation' => 'Кажется, я простыл.', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($distractors as [$sentence, $span, $correction]) {
        DB::table('example_distractors')->insert([
            'id' => Ulid::generate(), 'example_id' => $exampleId, 'sentence' => $sentence,
            'error_type' => 'article', 'error_span' => $span, 'correction' => $correction,
            'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $termId;
}

it('relabels a row whose sentence is fine and whose correction points nowhere', function () {
    // «the cold» → «cold» would teach «I think I have cold». The sentence breaks exactly one place, so
    // the true label is forced by the diff and the row is worth keeping.
    seedAuditTarget([['I think I have the cold.', 'the cold', 'cold']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->fixed['circular'] ?? 0)->toBe(1)
        ->and($outcome->totalDeleted())->toBe(0);

    $row = DB::table('example_distractors')->where('sentence', 'I think I have the cold.')->first();
    expect($row?->error_span)->toBe('the')
        ->and($row?->correction)->toBe('a');
});

it('deletes a row that breaks two places, which no single underline describes', function () {
    seedAuditTarget([['I think I has the cold today.', 'has', 'have']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['circular'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

it('deletes a row that is the example itself, with nothing to relabel', function () {
    seedAuditTarget([[AUDIT_EXAMPLE, 'a', 'the']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['equality'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

it('deletes the second of two sentences that normalise to the same thing', function () {
    seedAuditTarget([
        ["I think I've the cold.", 'the', 'a'],
        ['I think I have the cold.', 'the', 'a'],
    ], "I think I've a cold.");

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['dedup'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(1);
});

it('relabels a correction identical to its own span', function () {
    seedAuditTarget([['I think I have the cold.', 'the', 'the']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->fixed['noop'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->value('correction'))->toBe('a');
});

it('changes nothing on a dry run', function () {
    seedAuditTarget([['I think I have the cold.', 'the cold', 'cold']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: false));

    expect($outcome->totalFixed())->toBe(1)
        ->and(DB::table('example_distractors')->value('correction'))->toBe('cold');
});

it('is idempotent — a second pass over its own output finds nothing', function () {
    seedAuditTarget([['I think I have the cold.', 'the cold', 'cold']]);

    app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));
    $second = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($second->totalFixed())->toBe(0)
        ->and($second->totalDeleted())->toBe(0);
});

it('leaves a healthy row alone', function () {
    seedAuditTarget([['I think I have cold.', 'have cold', 'have a cold']]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->totalFixed())->toBe(0)
        ->and($outcome->totalDeleted())->toBe(0);
});
