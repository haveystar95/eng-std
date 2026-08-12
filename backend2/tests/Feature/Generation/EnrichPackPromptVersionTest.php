<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs the outbound call; wrap so those rows roll back.
uses(RefreshDatabase::class);

function fakeEnrichOpenAi(): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'distractors' => [], 'accepted_variants' => [], 'back_translation' => 'x', 'language_notes' => [],
        ])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);
}

function packWith(string $version): void
{
    (new OpenAiEnrichmentPacker(app(OutboundCallContext::class), 'key', 'gpt-4o-mini', $version))->pack(
        new EnrichmentBrief('01J0TERM', 'bank account', ['bank account'], 'банковский счёт',
            'I need a bank account to receive my salary.', 'Мне нужен банковский счёт.', 'en', 'ru'),
    );
}

/**
 * v2 answers the store5 proofread. Two classes of row survived v1 in numbers: determiner swaps that
 * are perfectly grammatical, and the same sentence twice with the contraction spelled out.
 */
it('adds the v2 rules: the determiner test and the no-re-spelling rule', function () {
    fakeEnrichOpenAi();
    packWith('v2');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'legal here only if the result is ungrammatical in EVERY context')
            // The three live rows, named — a rule with its own counter-examples is harder to misread
            // as generic advice than a rule stated in the abstract.
            && str_contains($system, 'I get along with **the** team well')
            && str_contains($system, 'Are **the** utilities included in the rent?')
            && str_contains($system, 'Could you explain fees…')
            && str_contains($system, 'Never re-spell a contraction')
            && str_contains($system, 'Do not repeat yourself')
            // The correction has to survive being applied — the same claim the validator checks.
            && str_contains($system, 'putting')
            // v1's content is inherited, not replaced.
            && str_contains($system, 'No markdown, ever')
            && str_contains($system, 'back_translation');
    });
});

it('keeps the v2 rules out of the frozen v1 prompt', function () {
    fakeEnrichOpenAi();
    packWith('v1');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'Never re-spell a contraction')
            && ! str_contains($system, 'ungrammatical in EVERY context');
    });
});

it('packs on v2 by default', function () {
    expect((string) config('services.generation.enrich_pack_prompt_version', 'v2'))->toBe('v2');
});
