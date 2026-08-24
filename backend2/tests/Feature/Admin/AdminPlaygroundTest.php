<?php

declare(strict_types=1);

use App\Modules\Admin\Application\Query\DryRunDistractorValidation;
use App\Modules\Admin\Application\Query\DryRunDistractorValidationHandler;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * A term with a pinned example, one distractor already stored against it, and one sentence that a
 * proofreader suppressed — the three facts a dry run has to tell apart.
 *
 * @return array{0: string, 1: string, 2: string}  [adminToken, termId, exampleId]
 */
function playgroundFixture(): array
{
    $user = User::factory()->create(['email' => 'playground@wt.test']);
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5, 'tier' => 'free']);
    [, $termId] = adminSeedTerm($user, 'Banking', 'withdraw money', 'снять деньги');

    $exampleId = Ulid::generate();
    DB::table('term_examples')->where('term_id', $termId)->delete();
    seedExample([
        'id' => $exampleId, 'term_id' => $termId,
        'sentence' => 'I would like to withdraw money from my account.',
        'translation' => 'Я хотел бы снять деньги со счёта.', 'source' => 'ai',
    ]);

    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId,
        'sentence' => 'I would like to withdraw money from my account since 2020.',
        'error_type' => 'tense', 'error_span' => 'since 2020', 'correction' => 'from my account',
        'generator_version' => 'mech-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    // Stored canonicalized, exactly as the suppression writer does.
    DB::table('enrichment_suppressions')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId,
        'sentence' => 'i would like to withdrawed money from my account',
        'source' => 'review', 'created_at' => now(),
    ]);

    return [adminActor()[1], $termId, $exampleId];
}

/** The row that survives every check: the example with exactly one fragment broken. */
function goodDistractorRow(): array
{
    return [
        'sentence' => 'I would like to withdrawing money from my account.',
        'error_span' => 'to withdrawing',
        'correction' => 'to withdraw',
        'error_type' => 'modal_to',
    ];
}

// ── The parity guard: the dry run IS the production verdict ──────────────────────────────────────

it('gives the same verdict as the production pipeline, row for row', function () {
    [, $termId] = playgroundFixture();

    $rows = [
        goodDistractorRow(),
        // Folds onto the accepted answer once our own normaliser has had it.
        ['sentence' => 'The withdraw money', 'error_span' => 'The', 'correction' => 'A', 'error_type' => 'article'],
        // The repair does not give back the example.
        ['sentence' => 'I would like to withdraw money at my account.', 'error_span' => 'at', 'correction' => 'into', 'error_type' => 'preposition'],
        // Already stored against this example.
        ['sentence' => 'I would like to withdraw money from my account since 2020.', 'error_span' => 'since 2020', 'correction' => 'from my account', 'error_type' => 'tense'],
    ];

    // What the станок would do: the same reader, the same candidate, the same validator — no gate log.
    $target = app(EnrichmentTargetReader::class)->byIds([TermId::fromString($termId)], 'ru')[$termId];
    $production = (new EnrichmentValidator())->validate(new EnrichmentCandidate(
        termId: $termId,
        acceptedForms: $target->acceptedForms,
        exampleId: $target->exampleId,
        exampleSentence: $target->exampleSentence,
        translation: $target->translation,
        exampleTranslation: $target->exampleTranslation,
        distractors: array_map(
            static fn (array $r): RawDistractor => new RawDistractor($r['sentence'], $r['error_type'], $r['error_span'], $r['correction']),
            $rows,
        ),
        variants: [],
        backTranslation: null,
        languageNotes: [],
        existingDistractors: $target->existingDistractors,
        backTranslationAsked: false,
    ));

    $dryRun = app(DryRunDistractorValidationHandler::class)(new DryRunDistractorValidation(
        items: $rows,
        termId: $termId,
    ));

    $keptByProduction = array_map(static fn (RawDistractor $d): string => $d->sentence, $production->distractors);
    $keptByDryRun = array_values(array_map(
        static fn ($row): string => $row->sentence,
        array_filter($dryRun->items, static fn ($row): bool => $row->kept),
    ));

    expect($keptByDryRun)->toBe($keptByProduction)
        ->and($dryRun->kept)->toBe(count($production->distractors))
        ->and($dryRun->total)->toBe(4);
});

it('leaves the validator itself unchanged whether or not the gate log is passed', function () {
    $candidate = new EnrichmentCandidate(
        termId: 'T', acceptedForms: ['withdraw money'], exampleId: 'E',
        exampleSentence: 'I would like to withdraw money from my account.',
        translation: null, exampleTranslation: null,
        distractors: [
            new RawDistractor('I would like to withdrawing money from my account.', 'modal_to', 'to withdrawing', 'to withdraw'),
            new RawDistractor('The withdraw money', 'article', 'The', 'A'),
        ],
        variants: [], backTranslation: null, backTranslationAsked: false,
    );

    $validator = new EnrichmentValidator();
    $without = $validator->validate($candidate);
    $with = $validator->validate($candidate, new App\Modules\Generation\Domain\Service\DistractorGateLog());

    expect(array_map(static fn (RawDistractor $d): string => $d->sentence, $with->distractors))
        ->toBe(array_map(static fn (RawDistractor $d): string => $d->sentence, $without->distractors))
        ->and($with->rejectedDistractors)->toBe($without->rejectedDistractors)
        ->and($with->proposedDistractors)->toBe($without->proposedDistractors);
});

// ── The other half of the promise: nothing is written ────────────────────────────────────────────

it('writes nothing at all — no distractors, no suppressions, no version marks', function () {
    [$token, $termId] = playgroundFixture();

    $before = [
        'distractors' => DB::table('example_distractors')->count(),
        'variants' => DB::table('term_accepted_variants')->count(),
        'suppressions' => DB::table('enrichment_suppressions')->count(),
        'versions' => DB::table('term_enrichment_versions')->count(),
        'findings' => DB::table('enrichment_findings')->count(),
        'enrichments' => DB::table('term_enrichments')->count(),
    ];

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/validate', [
            'items' => [goodDistractorRow(), ['sentence' => 'nonsense', 'error_span' => 'x', 'correction' => 'y']],
            'term_id' => $termId,
        ])
        ->assertOk()
        ->assertJsonPath('persisted', false);

    foreach ($before as $table => $count) {
        expect(DB::table(match ($table) {
            'distractors' => 'example_distractors',
            'variants' => 'term_accepted_variants',
            'suppressions' => 'enrichment_suppressions',
            'versions' => 'term_enrichment_versions',
            'findings' => 'enrichment_findings',
            'enrichments' => 'term_enrichments',
        })->count())->toBe($count, "{$table} changed — the sandbox wrote to the database");
    }
});

// ── Reasons ──────────────────────────────────────────────────────────────────────────────────────

it('names the gate that dropped each row, and tells a stored dupe from a suppressed one', function () {
    [$token, $termId] = playgroundFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/validate', [
            'term_id' => $termId,
            'items' => [
                goodDistractorRow(),
                // Already stored against the pinned example.
                ['sentence' => 'I would like to withdraw money from my account since 2020.', 'error_span' => 'since 2020', 'correction' => 'from my account', 'error_type' => 'tense'],
                // Suppressed by a review — the row is gone, the decision is not.
                ['sentence' => 'I would like to withdrawed money from my account.', 'error_span' => 'withdrawed', 'correction' => 'withdraw', 'error_type' => 'tense'],
                // The span is not in its own sentence.
                ['sentence' => 'I would like to withdraw money from my account today.', 'error_span' => 'zzz', 'correction' => 'qqq', 'error_type' => 'article'],
                // span === correction.
                ['sentence' => 'I would like to withdraw money from my bank.', 'error_span' => 'my', 'correction' => 'my', 'error_type' => 'article'],
                // A type outside the six.
                ['sentence' => 'I would like to withdraw money from my wallet.', 'error_span' => 'wallet', 'correction' => 'account', 'error_type' => 'vibes'],
            ],
        ])
        ->assertOk()
        ->json();

    $gates = array_column($body['items'], 'gate');
    $verdicts = array_column($body['items'], 'verdict');

    expect($verdicts[0])->toBe('KEEP')
        ->and($gates[0])->toBe('kept')
        ->and($gates[1])->toBe('duplicate_stored')
        ->and($gates[2])->toBe('duplicate_suppressed')
        ->and($gates[3])->toBe('span_not_found')
        ->and($gates[4])->toBe('no_op_correction')
        ->and($gates[5])->toBe('unknown_error_type')
        ->and($body['kept'])->toBe(1)
        ->and($body['total'])->toBe(6)
        ->and($body['source'])->toBe('term')
        ->and($body['suppressed_count'])->toBe(1);

    // Every reason is a sentence a person can read, not a code repeated.
    foreach ($body['items'] as $item) {
        expect($item['reason'])->not->toBe('')->and($item['reason'])->not->toBe($item['gate']);
    }
});

it('validates against a hand-typed reference when no term is chosen', function () {
    [$token] = playgroundFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/validate', [
            'manual' => [
                'term_text' => 'grain-free',
                'example_text' => 'This food is completely grain-free and organic.',
            ],
            'items' => [
                ['sentence' => 'This food is completely grain-free and a organic.', 'error_span' => 'a organic', 'correction' => 'organic', 'error_type' => 'article'],
                ['sentence' => 'This food is completely grain-free and organic.', 'error_span' => 'organic', 'correction' => 'organics', 'error_type' => 'article'],
            ],
        ])
        ->assertOk()
        ->json();

    expect($body['source'])->toBe('manual')
        ->and($body['term_text'])->toBe('grain-free')
        ->and($body['items'][0]['verdict'])->toBe('KEEP')
        // The second one IS the example.
        ->and($body['items'][1]['gate'])->toBe('equals_example')
        ->and($body['kept'])->toBe(1)
        ->and($body['matched_term_id'])->toBeNull();
});

it('says so when a row arrived without an error_type instead of failing it silently', function () {
    [$token] = playgroundFixture();

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/validate', [
            'manual' => ['term_text' => 'grain-free', 'example_text' => 'This food is completely grain-free and organic.'],
            'items' => [['sentence' => 'This food is completely grain-free and a organic.', 'error_span' => 'a organic', 'correction' => 'organic']],
        ])
        ->assertOk()
        ->json();

    expect($body['items'][0]['error_type_defaulted'])->toBeTrue()
        ->and($body['items'][0]['verdict'])->toBe('KEEP');
});

it('reports every row as unbuildable when there is no reference at all', function () {
    [$token] = playgroundFixture();

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/validate', ['items' => [goodDistractorRow()]])
        ->assertOk()
        ->assertJsonPath('items.0.gate', 'no_example')
        ->assertJsonPath('kept', 0);
});

// ── The model side ───────────────────────────────────────────────────────────────────────────────

it('lists every provider with its models, and says why an unusable one is unusable', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.anthropic.key' => '']);

    $rows = test()->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/playground/providers')
        ->assertOk()
        ->json('data');

    $byId = collect($rows)->keyBy('provider');

    expect($byId)->toHaveKey('openai')
        ->and($byId)->toHaveKey('anthropic')
        ->and($byId['anthropic']['available'])->toBeFalse()
        ->and($byId['anthropic']['reason'])->toContain('ANTHROPIC_API_KEY')
        // The OpenAI list is the models the project actually runs, deduplicated.
        ->and($byId['openai']['models'])->toContain(config('generation.core_model') ?? 'gpt-5.4')
        ->and(count($byId['openai']['models']))->toBe(count(array_unique($byId['openai']['models'])));
});

it('sends the prompt verbatim, with no system message and no schema', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.openai.key' => 'test-key']);
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini-2024-07-18',
        'choices' => [['message' => ['content' => '{"items":[{"sentence":"a"}]}']]],
        'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
    ], 200)]);

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', [
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt' => 'Верни JSON. Ничего больше.',
        ])
        ->assertOk()
        ->json();

    expect($body['parsed_json'])->toBe(['items' => [['sentence' => 'a']]])
        ->and($body['parse_error'])->toBeNull()
        ->and($body['error'])->toBeNull()
        ->and($body['usage']['tokens_in'])->toBe(1000)
        // 1000/1000 × $0.00015 + 1000/1000 × $0.0006
        ->and($body['usage']['cost_usd'])->toBe('0.000750')
        ->and($body['model'])->toBe('gpt-4o-mini-2024-07-18');

    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $data['messages'] === [['role' => 'user', 'content' => 'Верни JSON. Ничего больше.']]
            && ! array_key_exists('response_format', $data)
            && ! array_key_exists('temperature', $data);
    });
});

it('returns a provider failure as text rather than a 500', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.openai.key' => 'test-key']);
    Http::fake(['*' => Http::response(['error' => ['message' => 'insufficient_quota']], 429)]);

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', [
            'provider' => 'openai', 'model' => 'gpt-4o-mini', 'prompt' => 'hi',
        ])
        ->assertOk()
        ->json();

    expect($body['error'])->toContain('429')
        ->and($body['error'])->toContain('insufficient_quota')
        ->and($body['raw_text'])->toBe('');
});

it('refuses a provider with no key, and a model outside the registry, in words', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.anthropic.key' => '', 'playground.providers.openai.key' => 'test-key']);

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', ['provider' => 'anthropic', 'model' => 'claude-haiku-4-5', 'prompt' => 'hi'])
        ->assertOk()
        ->assertJsonPath('error', 'Anthropic: нет ключа (ANTHROPIC_API_KEY не задан)');

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', ['provider' => 'openai', 'model' => 'gpt-9000', 'prompt' => 'hi'])
        ->assertOk()
        ->assertJsonPath('error', 'модель «gpt-9000» не входит в список песочницы для «OpenAI».');
});

it('keeps unparseable text instead of calling it an error', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.openai.key' => 'test-key']);
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => 'Конечно! Вот ответ: не JSON.']]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ], 200)]);

    $body = test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'prompt' => 'hi'])
        ->assertOk()
        ->json();

    expect($body['parsed_json'])->toBeNull()
        ->and($body['parse_error'])->not->toBeNull()
        ->and($body['raw_text'])->toBe('Конечно! Вот ответ: не JSON.')
        ->and($body['error'])->toBeNull();
});

it('unwraps a fenced json block, because models fence constantly', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.openai.key' => 'test-key']);
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => "Вот:\n```json\n{\"a\": 1}\n```\n"]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
    ], 200)]);

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'prompt' => 'hi'])
        ->assertOk()
        ->assertJsonPath('parsed_json', ['a' => 1])
        ->assertJsonPath('parse_error', null);
});

it('sends temperature only when it was asked for', function () {
    [$token] = playgroundFixture();
    config(['playground.providers.openai.key' => 'test-key']);
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => '{}']]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);

    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/admin/api/playground/generate', [
            'provider' => 'openai', 'model' => 'gpt-4o-mini', 'prompt' => 'hi', 'temperature' => 0.2,
        ])->assertOk();

    Http::assertSent(fn (Request $request): bool => $request->data()['temperature'] === 0.2);
});

it('refuses an anonymous caller on every sandbox route', function () {
    test()->getJson('/admin/api/playground/providers')->assertUnauthorized();
    test()->postJson('/admin/api/playground/generate', [])->assertUnauthorized();
    test()->postJson('/admin/api/playground/validate', [])->assertUnauthorized();
});
