<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The догон (`enrich:backfill --topup=N`) and the version mark, which used to cancel each other out.
 *
 * A top-up picks terms by COVERAGE — «this pinned example is short of distractors» — precisely
 * because the version mark cannot express the reasons a processed term ends up short: a proofreader
 * deleted rows, the audit removed some, or the validator got better and the same prompt would now
 * keep more. The handler then re-filtered that list through the journal and dropped every term
 * already marked at the current version, which is all of them. The run printed «5 term(s) под догон»
 * and enriched zero.
 */
function topUpTerm(string $text, string $example): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_examples')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'sentence' => $example,
        'sentence_translation' => 'перевод примера', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => 'ru', 'text' => 'перевод',
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

function markEnriched(string $termId): void
{
    DB::table('term_enrichment_versions')->insert([
        'term_id' => $termId,
        'generator_version' => BuildTermEnrichmentsHandler::VERSION,
        'created_at' => now(),
    ]);
}

beforeEach(function () {
    config(['services.generation.driver' => 'fake']);
});

it('enriches a term the top-up chose, even though it is already marked at this version', function () {
    // The example matches the shape FakeEnrichmentPacker breaks, so its good row repairs back to it
    // and is actually stored — the assertion is "work happened", not just "the term was looked at".
    $termId = topUpTerm('deposit', 'I can deposit it.');
    markEnriched($termId);

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments(
        termIds: [$termId],
        generatorVersion: BuildTermEnrichmentsHandler::VERSION,
        translationLang: 'ru',
        ignoreVersionMark: true,
    ));

    expect($metrics->termsSeen)->toBe(1)
        ->and(DB::table('example_distractors')->count())->toBeGreaterThan(0);
});

it('still skips an already-marked term on an ordinary run', function () {
    // The other half: without a top-up the mark is the whole point — it is what stops a re-run from
    // paying for every term in the dictionary a second time.
    $termId = topUpTerm('lease', 'I can lease it.');
    markEnriched($termId);

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments(
        termIds: [$termId],
        generatorVersion: BuildTermEnrichmentsHandler::VERSION,
    ));

    expect($metrics->termsSeen)->toBe(0)
        ->and(DB::table('example_distractors')->count())->toBe(0);
});
