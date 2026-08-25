<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Command\RegenerateShowcase;
use App\Modules\Generation\Application\Command\RegenerateShowcaseHandler;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'key']);
    app()->forgetInstance(GenerationStackConfig::class);
    // This file WATCHES the станок's own call — `fakeCoreAndMechanics()` queues two responses and
    // the second one is the machinery's. The Feature suite binds FakeEnrichmentPacker over that port
    // (tests/Pest.php) so no test buys a model call by accident; here the real adapter is the
    // subject, with `Http::fake()` underneath it, so the instance is forgotten first.
    app()->forgetInstance(EnrichmentPackerPort::class);
});

/** A store term as the catalogue actually holds one: written by an old prompt, with an example. */
function legacyTerm(string $text, string $translation, string $version = 'legacy'): array
{
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, $text, $translation);

    DB::table('terms')->where('id', $termId)->update(['prompt_version' => $version, 'ipa' => null, 'cefr' => null]);
    DB::table('term_translations')->where('term_id', $termId)->update(['prompt_version' => $version]);

    $exampleId = Ulid::generate();
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => 'An old sentence about it.',
        'translation' => 'Старое предложение.', 'source' => 'ai', 'prompt_version' => $version,
    ]);

    return [$termId, $exampleId];
}

function fakeCoreAndMechanics(string $translation = 'арендатор'): void
{
    Http::fakeSequence()
        ->push([
            'model' => 'gpt-5.4-2026-03-05',
            'choices' => [['message' => ['content' => json_encode(['items' => [[
                'text' => 'tenant', 'type' => 'word', 'transcription' => 'ˈtenənt', 'translation' => $translation,
                'example' => 'The tenant pays the rent on time.',
                'example_translation' => 'Арендатор платит за аренду вовремя.', 'cefr' => 'B1',
                'image_api_prompt' => 'apartment keys', 'options' => ['a', 'b', 'c'], 'forms' => [],
            ]]])]]],
            'usage' => ['prompt_tokens' => 4000, 'completion_tokens' => 150],
        ], 200)
        ->push([
            'model' => 'gpt-4o-mini-2024-07-18',
            'choices' => [['message' => ['content' => json_encode(['items' => [[
                'text' => 'tenant', 'forms' => ['renter'],
                'distractors' => [[
                    'sentence' => 'The tenant pays the rent in time.',
                    'error_type' => 'preposition', 'error_span' => 'in time', 'correction' => 'on time',
                ]],
            ]]])]]],
            'usage' => ['prompt_tokens' => 3300, 'completion_tokens' => 120],
        ], 200);
}

it('replaces the core, stamps it, and moves the term out of the old vintage', function () {
    [$termId] = legacyTerm('tenant', 'житель');
    fakeCoreAndMechanics();

    $report = app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    expect($report->pending)->toBe(1)
        ->and($report->regenerated)->toBe(1)
        ->and($report->replaced[0])->toBe(['term' => 'tenant', 'was' => 'житель', 'now' => 'арендатор'])
        // The cursor is what resumes an interrupted pass.
        ->and($report->cursor)->toBe($termId);

    $term = DB::table('terms')->where('id', $termId)->first();
    $version = app(GenerationStackConfig::class)->corePromptVersion;
    expect($term->prompt_version)->toBe($version)
        ->and($term->generation_model)->toBe('gpt-5.4-2026-03-05')
        ->and($term->ipa)->toBe('ˈtenənt')
        ->and($term->cefr)->toBe('B1')
        // The term itself is NOT rewritten: a new text on an old id is a different term wearing this
        // one's progress.
        ->and($term->text)->toBe('tenant');

    expect(DB::table('term_translations')->where('term_id', $termId)->first())
        ->text->toBe('арендатор')
        ->prompt_version->toBe($version);

    expect(DB::table('term_examples')->where('term_id', $termId)->first())
        ->sentence->toBe('The tenant pays the rent on time.')
        ->source->toBe('ai')
        ->prompt_version->toBe($version);
});

it('rebuilds the machinery against the sentence that is there now', function () {
    [$termId, $exampleId] = legacyTerm('tenant', 'житель');
    // Machinery from the previous life of this card, built against the sentence being replaced.
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId,
        'sentence' => 'An old sentences about it.', 'error_type' => 'article',
        'error_span' => 'old sentences', 'correction' => 'old sentence',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    fakeCoreAndMechanics();

    app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    $sentences = DB::table('example_distractors')->where('example_id', $exampleId)->pluck('sentence')->all();

    // The stale one is gone (its sentence describes text nobody will see) and the fresh one is in,
    // on the SAME example row — nothing cascaded, so nothing else could have been lost with it.
    expect($sentences)->toBe(['The tenant pays the rent in time.'])
        ->and(DB::table('term_accepted_variants')->where('term_id', $termId)->pluck('text')->all())->toBe(['renter'])
        ->and(DB::table('term_enrichment_versions')->where('term_id', $termId)->value('generator_version'))
        ->toBe(BuildTermEnrichmentsHandler::VERSION);
});

it('leaves exactly one primary translation behind, whatever it found (A7)', function () {
    [$termId] = legacyTerm('tenant', 'житель');
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => 'ru',
        'text' => 'жилец', 'is_primary' => true, 'prompt_version' => 'legacy',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(DB::table('term_translations')->where('term_id', $termId)->where('is_primary', true)->count())->toBe(2);

    fakeCoreAndMechanics();
    app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    $primary = DB::table('term_translations')->where('term_id', $termId)->where('is_primary', true)->get();
    expect($primary)->toHaveCount(1)
        ->and($primary[0]->text)->toBe('арендатор')
        // The older reading survives as a row — it may be a useful alternative — it just stops
        // competing to be the question on the card.
        ->and(DB::table('term_translations')->where('term_id', $termId)->count())->toBe(2);
});

it('never touches progress or the review log', function () {
    [$termId] = legacyTerm('tenant', 'житель');
    $userId = (string) DB::table('users')->value('id');
    DB::table('user_term_progress')->updateOrInsert(
        ['user_id' => $userId, 'term_id' => $termId],
        [
            'state' => 'learning', 'reps' => 3, 'lapses' => 0, 'ease_factor' => 2.5,
            'interval_days' => 4, 'due_at' => now()->addDays(4), 'acquisition' => 'learning',
            'successful_reviews' => 2, 'created_at' => now(), 'updated_at' => now(),
        ],
    );
    fakeCoreAndMechanics();

    app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    expect(DB::table('user_term_progress')->where('term_id', $termId)->first())
        ->reps->toBe(3)
        ->successful_reviews->toBe(2)
        ->state->toBe('learning');
});

it('is idempotent: a regenerated term is not in the next pass', function () {
    legacyTerm('tenant', 'житель');
    fakeCoreAndMechanics();

    app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());
    Http::fake();   // any further call would be a re-spend

    $second = app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    expect($second->pending)->toBe(0)->and($second->attempted)->toBe(0);
    Http::assertNothingSent();
});

it('prices the sweep without calling or writing anything', function () {
    legacyTerm('tenant', 'житель');
    legacyTerm('landlord', 'хозяин', 'v9');
    Http::fake();

    $report = app(RegenerateShowcaseHandler::class)(new RegenerateShowcase(dryRun: true));

    expect($report->pending)->toBe(2)
        ->and($report->regenerated)->toBe(0)
        ->and($report->estimate)->not->toBeNull()
        ->and($report->estimate->terms)->toBe(2)
        // Priced from the real prompt lengths, so the estimate moves when a prompt is edited.
        ->and($report->estimate->coreTokensIn)->toBeGreaterThan(2000)
        ->and($report->estimate->totalUsd)->not->toBeNull()
        // Batch is half — and it is an estimate of a saving that is not implemented.
        ->and((float) $report->estimate->totalBatchUsd)
        ->toEqualWithDelta((float) $report->estimate->totalUsd / 2, 0.000001);

    Http::assertNothingSent();
    expect(DB::table('terms')->where('prompt_version', app(GenerationStackConfig::class)->corePromptVersion)->count())
        ->toBe(0);
});

it('does not blank a translation when the model answers without one', function () {
    [$termId] = legacyTerm('tenant', 'житель');
    Http::fake(['*' => Http::response([
        'model' => 'gpt-5.4',
        'choices' => [['message' => ['content' => json_encode(['items' => []])]]],
        'usage' => ['prompt_tokens' => 4000, 'completion_tokens' => 5],
    ], 200)]);

    $report = app(RegenerateShowcaseHandler::class)(new RegenerateShowcase());

    expect($report->regenerated)->toBe(0)
        ->and($report->failures)->toHaveCount(1)
        ->and(DB::table('term_translations')->where('term_id', $termId)->value('text'))->toBe('житель')
        // Still old, so the next pass will try again rather than treating it as done.
        ->and(DB::table('terms')->where('id', $termId)->value('prompt_version'))->toBe('legacy');
});
