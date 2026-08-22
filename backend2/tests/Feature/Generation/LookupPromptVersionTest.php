<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiWordLookup;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $answer */
function lookUpWith(string $version, array $answer = []): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode($answer + [
            'text' => 'occasion', 'type' => 'word', 'translation' => 'случай',
            'description' => 'A time when something happens.', 'example' => 'It was a special occasion.',
            'example_translation' => 'Это был особый случай.', 'cefr' => 'B1',
            'transcription' => 'əˈkeɪʒn', 'image_api_prompt' => 'birthday party celebration',
        ], JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);

    (new OpenAiWordLookup(app(OutboundCallContext::class), 'key', 'gpt-4o-mini', $version))
        ->lookUp(new WordLookupBrief('случай', new LanguageCode('en'), new LanguageCode('ru')));
}

it('tells v3 that the query may be in either language and that the card is about the English word', function () {
    lookUpWith('v3');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'either language')
            && str_contains($system, 'never an echo of the query');
    });
});

it('asks v3 whether the query is a word at all, and declares the field in the schema', function () {
    lookUpWith('v3');

    Http::assertSent(function (Request $request): bool {
        $schema = $request->data()['response_format']['json_schema']['schema'];

        return isset($schema['properties']['recognized'])
            // Strict Structured Outputs: a declared property that is not required is a 400.
            && in_array('recognized', $schema['required'], true);
    });
});

it('keeps the frozen v2 prompt and its schema exactly as they were', function () {
    lookUpWith('v2');

    Http::assertSent(function (Request $request): bool {
        $schema = $request->data()['response_format']['json_schema']['schema'];

        return ! isset($schema['properties']['recognized'])
            && ! in_array('recognized', $schema['required'], true)
            && ! str_contains($request->data()['messages'][0]['content'], 'either language');
    });
});

it('reads a refusal back without demanding the fields a refusal has no reason to carry', function () {
    // The prompt asks for empty strings when it cannot place the query. The parser must not trip
    // over them: a correct refusal turned into an exception would reach the learner as «не удалось
    // найти это слово», which says the app broke rather than «проверьте написание».
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'recognized' => false, 'text' => '', 'type' => 'word', 'translation' => '',
            'description' => '', 'example' => '', 'example_translation' => '', 'cefr' => '',
            'transcription' => '', 'image_api_prompt' => '',
        ])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);

    $result = (new OpenAiWordLookup(app(OutboundCallContext::class), 'key', 'gpt-4o-mini', 'v3'))
        ->lookUp(new WordLookupBrief('asdfgh', new LanguageCode('en'), new LanguageCode('ru')));

    expect($result->notRecognized)->toBeTrue()
        // Nothing worth storing but the verdict itself.
        ->and($result->toPayload())->toBe(['not_recognized' => true]);
});
