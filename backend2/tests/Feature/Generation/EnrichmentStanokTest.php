<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Generation\Domain\ValueObject\RawLanguageNote;
use App\Modules\Generation\Domain\ValueObject\RawVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const ENRICH_TERM_ID = '01J000000000000000000000TM';
const ENRICH_EXAMPLE_ID = '01J000000000000000000000EX';

function seedEnrichmentTerm(string $sentence = 'I would like to withdraw money.'): void
{
    DB::table('terms')->insert([
        'id' => ENRICH_TERM_ID,
        'lang' => 'en',
        'text' => 'withdraw money',
        'normalized_text' => 'withdraw money',
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => str_pad('01TR', 26, '0'),
        'term_id' => ENRICH_TERM_ID,
        'lang' => 'ru',
        'text' => 'снять деньги',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    seedExample([
        'id' => ENRICH_EXAMPLE_ID,
        'term_id' => ENRICH_TERM_ID,
        'sentence' => $sentence,
        'translation' => 'Я хотел бы снять деньги.',
        'source' => 'ai',
    ]);
}

/**
 * A packer that returns a fixed pack and counts how many times it was asked. The count IS the
 * assertion for idempotency: "did we pay the model again?".
 */
function countingEnrichmentPacker(EnrichmentPack $pack): object
{
    return new class($pack) implements EnrichmentPackerPort
    {
        public int $calls = 0;

        public function __construct(private readonly EnrichmentPack $pack) {}

        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            $this->calls++;

            return $this->pack;
        }
    };
}

function enrichPack(
    array $distractors = [],
    array $variants = [],
    ?string $backTranslation = 'withdraw money',
    array $synonyms = [],
): EnrichmentPack {
    return new EnrichmentPack($distractors, $variants, $backTranslation, [], 'test', 10, 20, synonyms: $synonyms);
}

// SYN-1e: the станок is not a synonym writer any more, and that is a decision in code rather than a
// flag position. Three measured prompt iterations (v14 → v14.1 → v14.2) could not get the machinery's
// accuracy where a written row has to be; synonyms are a CORE product now, with one producer and one
// place the accuracy question is answered. The switch still governs its two real doors, so the test
// turns it ON — a flag that cannot re-open this path is the property being pinned.
it('writes no synonyms from the станок at any flag value', function () {
    config(['services.generation.write_synonyms' => true]);
    seedEnrichmentTerm();
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(enrichPack(
        synonyms: ['take out money', 'draw money'],
    )));

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-syn'));

    expect(DB::table('term_synonyms')->where('term_id', ENRICH_TERM_ID)->count())->toBe(0)
        // Still JUDGED and still counted: a run's numbers stay readable without a row being written,
        // which is what lets the product be evaluated from a real run instead of from a guess. The
        // count that shows it here is the REJECTED one — this fixture's term is a phrase, and the
        // shape rules refuse synonyms for a phrase before accuracy is ever in question.
        ->and($metrics->synonymsRejected)->toBe(2);
});

it('writes validated distractors and variants, and marks the term at the version', function () {
    seedEnrichmentTerm();
    $packer = countingEnrichmentPacker(enrichPack(
        distractors: [new RawDistractor('I would like to withdrawing money.', 'modal_to', 'to withdrawing', 'to withdraw')],
        variants: [new RawVariant('take out money', 'то же значение')],
    ));
    app()->instance(EnrichmentPackerPort::class, $packer);

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect(DB::table('example_distractors')->where('example_id', ENRICH_EXAMPLE_ID)->count())->toBe(1)
        ->and(DB::table('term_accepted_variants')->where('term_id', ENRICH_TERM_ID)->value('text'))->toBe('take out money')
        ->and(DB::table('term_enrichment_versions')->where('term_id', ENRICH_TERM_ID)->where('generator_version', 'enrich-test')->exists())->toBeTrue()
        ->and($metrics->distractorsWritten)->toBe(1)
        ->and($metrics->variantsWritten)->toBe(1);
});

it('does not call the model again for a term already marked at that version', function () {
    seedEnrichmentTerm();
    $packer = countingEnrichmentPacker(enrichPack(variants: [new RawVariant('take out money', null)]));
    app()->instance(EnrichmentPackerPort::class, $packer);

    $handler = app(BuildTermEnrichmentsHandler::class);
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect($packer->calls)->toBe(1)
        ->and(DB::table('term_accepted_variants')->where('term_id', ENRICH_TERM_ID)->count())->toBe(1);
});

it('marks a term that produced nothing, so a re-run does not keep paying for the same silence', function () {
    seedEnrichmentTerm();
    $packer = countingEnrichmentPacker(enrichPack());
    app()->instance(EnrichmentPackerPort::class, $packer);

    $handler = app(BuildTermEnrichmentsHandler::class);
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect($packer->calls)->toBe(1)
        ->and(DB::table('term_enrichment_versions')->where('term_id', ENRICH_TERM_ID)->count())->toBe(1);
});

it('runs again for a NEW version and does not duplicate the variant it already stored', function () {
    seedEnrichmentTerm();
    $packer = countingEnrichmentPacker(enrichPack(variants: [new RawVariant('take out money', null)]));
    app()->instance(EnrichmentPackerPort::class, $packer);

    $handler = app(BuildTermEnrichmentsHandler::class);
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-v1'));
    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-v2'));

    // Paid twice (a new version is a new question), stored once (the unique index holds).
    expect($packer->calls)->toBe(2)
        ->and(DB::table('term_accepted_variants')->where('term_id', ENRICH_TERM_ID)->count())->toBe(1);
});

it('persists a Ukrainian leakage finding for a Ukrainian word in the Russian field', function () {
    seedEnrichmentTerm();
    DB::table('term_translations')->where('term_id', ENRICH_TERM_ID)->update(['text' => 'знімати гроші']);
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(enrichPack()));

    app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    // `ua_leakage`, not the coarse `language`: the repair is a regeneration, and the CHECK on the
    // column has to accept the split kinds for the row to land at all.
    expect(DB::table('enrichment_findings')->where('term_id', ENRICH_TERM_ID)->where('kind', 'ua_leakage')->exists())->toBeTrue();
});

it('persists a nonword finding from the model — the case no charset check can see', function () {
    seedEnrichmentTerm();
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(new EnrichmentPack(
        [], [], 'withdraw money',
        [new RawLanguageNote('misspelled_or_nonword', '«колледка» — не слово; должно быть «коллега».')],
        'test', 10, 20,
    )));

    app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect(DB::table('enrichment_findings')->where('term_id', ENRICH_TERM_ID)->where('kind', 'misspelled_or_nonword')->exists())->toBeTrue();
});

it('counts a term whose pack throws as failed without taking down the rest of the chunk', function () {
    seedEnrichmentTerm();
    app()->instance(EnrichmentPackerPort::class, new class implements EnrichmentPackerPort
    {
        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            throw new RuntimeException('provider down');
        }
    });

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect($metrics->termsFailed)->toBe(1)
        // Not marked: the next run must retry it rather than write it off.
        ->and(DB::table('term_enrichment_versions')->where('term_id', ENRICH_TERM_ID)->exists())->toBeFalse();
});

// The станок's spend ledger. `term_enrichments` is what the admin's per-collection cost reads for
// the "Станок" row, and the pack path never wrote it — so every collection reported станок = 0 while
// the calls kept happening (3 rows in the live table, all from before the pack path landed).
it('records the spend of every enrichment call', function () {
    seedEnrichmentTerm();
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(
        enrichPack(variants: [new RawVariant('take out money', null)]),
    ));
    $handler = app(BuildTermEnrichmentsHandler::class);

    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-spend-1'));

    $row = DB::table('term_enrichments')->where('term_id', ENRICH_TERM_ID)->first();

    expect($row)->not->toBeNull();
    expect($row->tokens_in)->not->toBeNull();
    expect((float) $row->cost_usd)->toBeGreaterThanOrEqual(0.0);
});

// A pack the validator throws away cost exactly what a good one costs. Booking only the successful
// ones would make the worst runs look free — the same rule GenerationPipeline follows for generation.
it('books the call even when the validator keeps nothing from it', function () {
    seedEnrichmentTerm();
    // An empty pack: nothing survives to any table, and the call still cost money.
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(enrichPack()));
    $handler = app(BuildTermEnrichmentsHandler::class);

    $handler(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-spend-2'));

    expect(DB::table('term_accepted_variants')->where('term_id', ENRICH_TERM_ID)->count())->toBe(0);

    expect(DB::table('term_enrichments')->where('term_id', ENRICH_TERM_ID)->count())->toBe(1);
});

/**
 * MECH-1 — a term that throws is skipped, and SAID SO.
 *
 * The per-term catch is right and stays: one malformed pack must not take down the other nineteen.
 * What was wrong is that it bound nothing and logged nothing, so a run of live paid calls with dead
 * terms in it looked exactly like a clean one — the only thing that moved was a count in a metrics
 * object the job never printed.
 */
it('enriches the rest of the batch when one term throws, and names the one that died', function () {
    seedEnrichmentTerm();

    // A second term beside the first, so «the batch survived» is an observation and not a tautology.
    $otherId = '01J000000000000000000000T2';
    DB::table('terms')->insert([
        'id' => $otherId, 'lang' => 'en', 'text' => 'open an account', 'normalized_text' => 'open an account',
        'type' => 'phrase', 'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => str_pad('01TR2', 26, '0'), 'term_id' => $otherId, 'lang' => 'ru',
        'text' => 'открыть счёт', 'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // The packer dies on exactly one term — the shape of a malformed answer for one word, which is
    // the case the blanket catch exists for and the case it used to hide.
    app()->instance(EnrichmentPackerPort::class, new class(enrichPack(variants: [new RawVariant('withdrew money', null)])) implements EnrichmentPackerPort
    {
        public function __construct(private readonly EnrichmentPack $pack) {}

        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            if ($brief->text === 'open an account') {
                throw new RuntimeException('unparseable pack');
            }

            return $this->pack;
        }
    });

    $metrics = app(BuildTermEnrichmentsHandler::class)(
        new BuildTermEnrichments([ENRICH_TERM_ID, $otherId], 'enrich-mech1'),
    );

    // The healthy term was enriched — the failure did not take the batch with it.
    expect(DB::table('term_accepted_variants')->where('term_id', ENRICH_TERM_ID)->count())->toBe(1);

    // Counted…
    expect($metrics->termsSeen)->toBe(2)
        ->and($metrics->termsFailed)->toBe(1)
        ->and($metrics->hasFailures())->toBeTrue()
        // …and summarised in one line the caller can print.
        ->and($metrics->failureSummary())->toBe('1 of 2 failed');

    // …and, the part that was missing entirely: WHICH term, and WHY.
    expect($metrics->failures)->toHaveCount(1)
        ->and($metrics->failures[0]['term_id'])->toBe($otherId)
        ->and($metrics->failures[0]['text'])->toBe('open an account')
        ->and($metrics->failures[0]['reason'])->toContain('unparseable pack')
        // The class as well as the message, so an exception with an empty message still says something.
        ->and($metrics->failures[0]['reason'])->toContain('RuntimeException');
});

it('says «0 of N failed» on a clean run rather than saying nothing', function () {
    seedEnrichmentTerm();
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(enrichPack()));

    $metrics = app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enr-mech1-ok'));

    expect($metrics->hasFailures())->toBeFalse()
        ->and($metrics->failures)->toBe([])
        ->and($metrics->failureSummary())->toBe('0 of 1 failed');
});
