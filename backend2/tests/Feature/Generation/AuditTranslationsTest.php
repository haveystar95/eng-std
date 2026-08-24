<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\AuditTranslations;
use App\Modules\Generation\Application\Command\AuditTranslationsHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'key']);
});

/** A term with a stored translation and example, ready to be second-guessed. */
function auditTerm(string $text, string $translation, ?string $exampleTranslation = null): string
{
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, $text, $translation);

    if ($exampleTranslation !== null) {
        seedExample([
            'term_id' => $termId, 'sentence' => 'A sentence.',
            'translation' => $exampleTranslation, 'source' => 'ai',
        ]);
    }

    return $termId;
}

function fakeFreshCore(string $translation): void
{
    Http::fake(['*' => Http::response([
        'model' => 'gpt-5.4-2026-03-05',
        'choices' => [['message' => ['content' => json_encode(['items' => [[
            'text' => 'tenant', 'type' => 'word', 'transcription' => 'ˈtenənt',
            'translation' => $translation, 'example' => 'The tenant paid the rent.',
            'example_translation' => 'Арендатор заплатил за аренду.', 'cefr' => 'B1',
            'image_api_prompt' => 'apartment keys handover', 'options' => ['a', 'b', 'c'], 'forms' => [],
        ]]])]]],
        'usage' => ['prompt_tokens' => 30, 'completion_tokens' => 40],
    ], 200)]);
}

it('flags a term where an independent rendering disagrees with the database', function () {
    $termId = auditTerm('tenant', 'житель');
    fakeFreshCore('арендатор');

    $outcome = app(AuditTranslationsHandler::class)(new AuditTranslations(termIds: [$termId]));

    expect($outcome->termsSeen)->toBe(1)
        ->and($outcome->disagreements)->toHaveCount(1)
        ->and($outcome->disagreements[0]['stored'])->toBe('житель')
        ->and($outcome->disagreements[0]['fresh'])->toBe('арендатор');

    // Written as a finding under the audit's OWN version, never as a станок mark: a report that made
    // terms look processed would stop the станок from ever coming back to them.
    $finding = DB::table('enrichment_findings')->where('term_id', $termId)->first();
    expect($finding->kind)->toBe('ambiguity')
        ->and($finding->generator_version)->toBe(AuditTranslationsHandler::VERSION)
        ->and(DB::table('term_enrichment_versions')->count())->toBe(0);
});

it('says nothing when the two renderings are the same answer', function () {
    $termId = auditTerm('tenant', 'арендатор');
    fakeFreshCore('Арендатор.');   // same answer: case and punctuation are folded, as everywhere else

    $outcome = app(AuditTranslationsHandler::class)(new AuditTranslations(termIds: [$termId]));

    expect($outcome->disagreements)->toBe([])
        ->and(DB::table('enrichment_findings')->count())->toBe(0);
});

it('never shows the model the stored translation — a second opinion that saw the first one is not one', function () {
    $termId = auditTerm('tenant', 'житель');
    fakeFreshCore('арендатор');

    app(AuditTranslationsHandler::class)(new AuditTranslations(termIds: [$termId]));

    Http::assertSent(fn (Request $request): bool => str_contains($request->data()['messages'][1]['content'], 'tenant')
        && ! str_contains($request->data()['messages'][1]['content'], 'житель'));
});

/**
 * The half of the sweep that needs no network: letters that do not belong to the field's language.
 * It sees Ukrainian `і/ї/є/ґ` and foreign scripts; a Ukrainian word spelled in letters Russian
 * shares is invisible to it, and is left to the disagreement test above — which catches it, because
 * an independent rendering writes the Russian word.
 */
it('catches foreign letters in a Russian field without asking a model at all', function () {
    $termId = auditTerm('tenant', 'арендатор');
    DB::table('term_translations')->where('term_id', $termId)->update(['text' => 'потрібно']);
    fakeFreshCore('потрібно');   // the model agrees with the row; only the letters are the problem

    $outcome = app(AuditTranslationsHandler::class)(new AuditTranslations(termIds: [$termId]));

    expect(collect($outcome->findings)->pluck('kind')->map->value->all())->toContain('language');
});

it('calls nothing and writes nothing on a dry run', function () {
    $termId = auditTerm('tenant', 'житель');
    Http::fake();

    $outcome = app(AuditTranslationsHandler::class)(new AuditTranslations(termIds: [$termId], dryRun: true));

    expect($outcome->termsSeen)->toBe(1)
        ->and($outcome->findings)->toBe([]);
    Http::assertNothingSent();
});
