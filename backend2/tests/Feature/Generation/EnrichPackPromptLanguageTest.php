<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs the outbound call; wrap so those rows roll back.
uses(RefreshDatabase::class);

/**
 * The станок's prompt must name no language of its own — the direction is a property of the content.
 * A hardcoded "Russian speaker" would silently produce Russian-interference distractors for, say, a
 * Spanish learner, and nothing downstream would notice: the JSON would be perfectly well-formed and
 * the validator is language-agnostic by design. So the guard has to live here, at the prompt.
 */
function packFor(string $termLang, string $translationLang): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'distractors' => [], 'accepted_variants' => [], 'back_translation' => '', 'language_notes' => [],
        ])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);

    (new OpenAiEnrichmentPacker('key', 'gpt-4o-mini'))->pack(new EnrichmentBrief(
        termId: '01TERM000000000000000000A',
        text: 'withdraw money',
        acceptedForms: ['withdraw money'],
        translation: 'снять деньги',
        exampleSentence: 'I would like to withdraw money.',
        exampleTranslation: 'Я хотел бы снять деньги.',
        termLang: $termLang,
        translationLang: $translationLang,
    ));
}

function sentSystemPrompt(): string
{
    $captured = '';
    Http::assertSent(function (Request $request) use (&$captured): bool {
        $captured = (string) $request->data()['messages'][0]['content'];

        return true;
    });

    return $captured;
}

it('names the learner language from the brief, not a hardcoded one', function () {
    packFor('en', 'ru');

    $system = sentSystemPrompt();

    expect($system)->toContain('native speaker of Russian learning English')
        ->and($system)->not->toContain('{{translation_lang}}')
        ->and($system)->not->toContain('{{term_lang}}');
});

it('renders a different pair without a trace of Russian', function () {
    packFor('en', 'es');

    $system = sentSystemPrompt();

    expect($system)->toContain('native speaker of Spanish learning English')
        // The whole point: no language name leaks in from the template itself.
        ->and($system)->not->toContain('Russian');
});

it('keeps the error taxonomy language-independent — no baked-in calques', function () {
    packFor('en', 'es');

    $system = sentSystemPrompt();

    // The classes stay (the schema's enum depends on them)…
    expect($system)->toContain('`article`')
        ->and($system)->toContain('`preposition`')
        ->and($system)->toContain('`false_friend`')
        ->and($system)->toContain('`modal_to`')
        // …but the concrete mistakes are derived by the model, not listed for one pair.
        ->and($system)->not->toContain('I can to swim')
        ->and($system)->not->toContain('depends from')
        ->and($system)->not->toContain('здаватися');
});

it('falls back to the raw code for a language it has no name for', function () {
    packFor('en', 'kk');

    expect(sentSystemPrompt())->toContain('native speaker of kk learning English');
});
