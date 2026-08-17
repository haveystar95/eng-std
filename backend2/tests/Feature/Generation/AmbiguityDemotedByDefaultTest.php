<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * store5 (01a4380): of 41 open flags, kind=ambiguity ("переформулировать") accounted for 31 and the
 * review found none of them useful — back-translation trivia. The finding still has to land in the
 * journal (nothing about detecting it changes); only the routine report and the proofreading export
 * get to stay quiet about it unless asked.
 */
function ambiguityOnlyPacker(): EnrichmentPackerPort
{
    return new class implements EnrichmentPackerPort
    {
        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            // A back-translation the accepted forms do not cover, no variant to close the gap and no
            // distractor — an ambiguity-only row, nothing else for the term to be exported for.
            return new EnrichmentPack([], [], 'take cash out', [], 'test', 10, 20);
        }
    };
}

function seedAmbiguityTerm(string $collectionId, string $termId): void
{
    DB::table('collections')->insert([
        'id' => $collectionId,
        'type' => 'system',
        'title' => 'Тест',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'visibility' => 'public',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('terms')->insert([
        'id' => $termId,
        'lang' => 'en',
        'text' => 'withdraw money',
        'normalized_text' => 'withdraw money',
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(),
        'term_id' => $termId,
        'lang' => 'ru',
        'text' => 'снять деньги',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(),
        'collection_id' => $collectionId,
        'term_id' => $termId,
        'position' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('leaves kind=ambiguity out of the report and the export by default', function () {
    $collectionId = Ulid::generate();
    $termId = Ulid::generate();
    seedAmbiguityTerm($collectionId, $termId);
    app()->instance(EnrichmentPackerPort::class, ambiguityOnlyPacker());

    $path = storage_path('app/ambiguity-demote-test.md');
    @unlink($path);

    $this->artisan('enrich:backfill', ['--collection' => [$collectionId], '--out' => $path])
        ->assertSuccessful()
        ->expectsOutputToContain('% языковых флагов')
        ->doesntExpectOutputToContain('% ambiguous');

    // Still journaled — demotion is a display concern, not a detection one.
    expect(DB::table('enrichment_findings')->where('term_id', $termId)->where('kind', 'ambiguity')->exists())->toBeTrue();

    $body = (string) file_get_contents($path);
    expect($body)->not->toContain('переформулировать')
        // Nothing else to show for this term once the only flag is filtered out.
        ->and($body)->not->toContain('withdraw money');

    @unlink($path);
});

it('includes kind=ambiguity in the report and the export with --include-ambiguity', function () {
    $collectionId = Ulid::generate();
    $termId = Ulid::generate();
    seedAmbiguityTerm($collectionId, $termId);
    app()->instance(EnrichmentPackerPort::class, ambiguityOnlyPacker());

    $path = storage_path('app/ambiguity-include-test.md');
    @unlink($path);

    $this->artisan('enrich:backfill', [
        '--collection' => [$collectionId],
        '--out' => $path,
        '--include-ambiguity' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('% ambiguous');

    $body = (string) file_get_contents($path);
    expect($body)->toContain('переформулировать')
        ->and($body)->toContain('withdraw money');

    @unlink($path);
});
