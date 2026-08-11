<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
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
    DB::table('term_examples')->insert([
        'id' => ENRICH_EXAMPLE_ID,
        'term_id' => ENRICH_TERM_ID,
        'sentence' => $sentence,
        'sentence_translation' => 'Я хотел бы снять деньги.',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
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
        distractors: [new RawDistractor('I can to withdraw money.', 'modal_to', 'can to', 'can')],
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

it('persists a language finding for a Ukrainian word in the Russian field', function () {
    seedEnrichmentTerm();
    DB::table('term_translations')->where('term_id', ENRICH_TERM_ID)->update(['text' => 'знімати гроші']);
    app()->instance(EnrichmentPackerPort::class, countingEnrichmentPacker(enrichPack()));

    app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([ENRICH_TERM_ID], 'enrich-test'));

    expect(DB::table('enrichment_findings')->where('term_id', ENRICH_TERM_ID)->where('kind', 'language')->exists())->toBeTrue();
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
