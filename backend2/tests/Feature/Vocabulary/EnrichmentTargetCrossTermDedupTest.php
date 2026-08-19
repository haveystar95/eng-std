<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Live gap found by the topup-3 proofreading pass (2026-08-19): two `terms` rows whose text is the
 * same phrase once normalised (a case/whitespace-only duplicate — the `terms` unique index does not
 * normalise, so it never caught these) each got their own instance of the identical distractor
 * sentence, because {@see EnrichmentValidator}'s dedup only ever saw ONE term's own stored rows.
 * 8 live instances were removed by hand in that review; this is the gate against a repeat.
 */
const DUP_TEXT_A = 'housekeeping';

const DUP_TEXT_B = 'Housekeeping ';   // same phrase once normalised (case + trailing space)

const DUP_EXAMPLE = 'I asked housekeeping to clean my room while I was out.';

const DUP_SENTENCE = 'I asked housekeeping to clean my room for I was out.';

function seedDuplicateTextTerm(string $text): string
{
    $termId = Ulid::generate();
    $exampleId = Ulid::generate();

    DB::table('terms')->insert([
        'id' => $termId, 'text' => $text, 'normalized_text' => $text,
        'lang' => 'en', 'type' => 'phrase', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_examples')->insert([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => DUP_EXAMPLE,
        'sentence_translation' => 'Я попросил горничную убрать в номере, пока меня не было.', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

// Named distinctly from EnrichmentSuppressionTest's own `packerReturning()` — under
// `--parallel`, paratest groups files into worker processes in an order this file does not
// control, so a same-named global function declared in another test file is not reliably
// available here.
function crossTermDedupPackerReturning(EnrichmentPack $pack): EnrichmentPackerPort
{
    return new class($pack) implements EnrichmentPackerPort
    {
        public function __construct(private readonly EnrichmentPack $pack) {}

        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            return $this->pack;
        }
    };
}

it("folds a sibling term's stored distractors into existingDistractors", function () {
    $termA = seedDuplicateTextTerm(DUP_TEXT_A);
    $termB = seedDuplicateTextTerm(DUP_TEXT_B);

    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(),
        'example_id' => DB::table('term_examples')->where('term_id', $termA)->value('id'),
        'sentence' => DUP_SENTENCE, 'error_type' => 'preposition', 'error_span' => 'for', 'correction' => 'while',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $views = app(EnrichmentTargetReader::class)->byIds([TermId::fromString($termB)], 'ru');

    expect($views[$termB]->existingDistractors)->toContain(DUP_SENTENCE);
});

it('does not write the identical distractor to a duplicate-text sibling term', function () {
    $termA = seedDuplicateTextTerm(DUP_TEXT_A);
    $termB = seedDuplicateTextTerm(DUP_TEXT_B);

    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(),
        'example_id' => DB::table('term_examples')->where('term_id', $termA)->value('id'),
        'sentence' => DUP_SENTENCE, 'error_type' => 'preposition', 'error_span' => 'for', 'correction' => 'while',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // The pack proposes the SAME sentence for termB (exactly what topup-3 did live) alongside a
    // genuinely new, distinct one.
    $pack = new EnrichmentPack(
        distractors: [
            new RawDistractor(DUP_SENTENCE, 'preposition', 'for', 'while'),
            new RawDistractor('I asked housekeeping to clean my rooms while I was out.', 'tense', 'rooms', 'room'),
        ],
        variants: [],
        backTranslation: DUP_TEXT_B,
        languageNotes: [],
        model: 'test',
        tokensIn: 10,
        tokensOut: 20,
    );
    app()->instance(EnrichmentPackerPort::class, crossTermDedupPackerReturning($pack));

    app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([$termB], 'enrich-topup'));

    $stored = DB::table('example_distractors')
        ->where('example_id', DB::table('term_examples')->where('term_id', $termB)->value('id'))
        ->pluck('sentence');

    expect($stored)->not->toContain(DUP_SENTENCE)
        ->and($stored)->toContain('I asked housekeeping to clean my rooms while I was out.')
        ->and($stored)->toHaveCount(1);
});
