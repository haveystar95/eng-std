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

function enrichPack(array $distractors = [], array $variants = [], ?string $backTranslation = 'withdraw money'): EnrichmentPack
{
    return new EnrichmentPack($distractors, $variants, $backTranslation, [], 'test', 10, 20);
}

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
