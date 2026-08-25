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
function seedAuditTarget(
    array $distractors,
    string $example = AUDIT_EXAMPLE,
    string $lang = 'en',
    string $termText = 'cold',
): string {
    $termId = Ulid::generate();
    $exampleId = Ulid::generate();

    DB::table('terms')->insert([
        'id' => $termId, 'text' => $termText, 'normalized_text' => $termText,
        'lang' => $lang, 'type' => 'word', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => $example,
        'translation' => 'Кажется, я простыл.', 'source' => 'ai',
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

// Live case from the enrich-v2-backfill review (E.2): before the fix this row passed every check —
// equality missed it because normalize() never folds a bare "'s" onto "is" outside the curated
// pronoun list, and the circular check (which DOES fold "Here's" via canonicalize) then judged the
// row's own repair correct, so it was never flagged as either broken or mislabelled. It reads as the
// example with the contraction spelled out, so there is nothing to relabel — it is scrap, not a fix.
it('deletes a row that is the example with a bare apostrophe-s spelled out, caught only by equality', function () {
    seedAuditTarget([['Here is a little bit about me.', 'Here is', "Here's"]], "Here's a little bit about me.");

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['equality'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

// The two classes the retro-audit could not see until the gates existed. Both are SCRAP, not relabel:
// the sentence is what is wrong, so no span/correction pair would rescue the row.

it('deletes a stored row that is the example with a contraction spelled out', function () {
    // The live «Piece of cake» shape, retro. Equality now asks by clitic, so «it will» folds onto
    // «it'll»; before this it did not, and the row passed all four checks on its way through the audit.
    seedAuditTarget(
        [["Don't worry about the test — it will be a piece of cake.", 'it will', "it'll"]],
        "Don't worry about the test — it'll be a piece of cake.",
    );

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['equality'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

it('deletes a stored row written in the wrong alphabet', function () {
    seedAuditTarget(
        [['He always knows how to начать a conversation.', 'начать', 'start']],
        'He always knows how to start a conversation.',
    );

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['script'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

it('deletes a stored POLISH row carrying Cyrillic, which a language-name check would have passed', function () {
    seedAuditTarget(
        [['Nasza rozmowa trwała к поздna.', 'к поздna', 'do późna']],
        'Nasza rozmowa trwała do późna.',
        lang: 'pl',
        termText: 'rozmowa',
    );

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['script'] ?? 0)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});

it('leaves a correct Polish row alone', function () {
    seedAuditTarget(
        [['Nasza rozmowa trwał do późna.', 'trwał', 'trwała']],
        'Nasza rozmowa trwała do późna.',
        lang: 'pl',
        termText: 'rozmowa',
    );

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->totalDeleted())->toBe(0)
        ->and(DB::table('example_distractors')->count())->toBe(1);
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
