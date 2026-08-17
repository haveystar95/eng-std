<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ExampleRegenBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiExampleRegenerator;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs each outbound call; wrap so those rows roll back.
uses(RefreshDatabase::class);

/**
 * «New example» must produce an example OF THE TERM (приёмка 17.08).
 *
 * The regeneration run yesterday answered «How much does this bag cost?» with «How much does that
 * coat cost?» — a well-formed sentence about a different object, in which the term does not occur at
 * all. The intro card had nothing to set in bold and the learner was shown a word they never asked
 * about. The prompt is where we ask for it; the check at the door is where we get it.
 */
function fakeExample(string $example): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'example' => $example,
            'example_translation' => 'перевод',
        ])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);
}

function regenerateExample(string $term, string $version = 'v2'): App\Modules\Generation\Application\Dto\ExampleRegenResult
{
    return (new OpenAiExampleRegenerator(app(OutboundCallContext::class), 'key', 'gpt-4o-mini', $version))
        ->regenerate(new ExampleRegenBrief(
            text: $term,
            termLang: new LanguageCode('en'),
            translationLang: new LanguageCode('ru'),
            avoid: 'How much does that coat cost?',
        ));
}

it('accepts an example that contains the term verbatim', function () {
    fakeExample('How much does this bag cost if I take two?');

    $result = regenerateExample('How much does this bag cost?');

    expect($result->example)->toBe('How much does this bag cost if I take two?');
});

it('refuses the very sentence that shipped — the term is not in it', function () {
    fakeExample('How much does that coat cost?');

    expect(fn () => regenerateExample('How much does this bag cost?'))
        ->toThrow(RuntimeException::class, 'without the term');
});

it('allows the term to shed its own trailing mark, exactly as the card does', function () {
    // «I have a fever.» inside «I have a fever and feel very weak.» — the full stop belongs to the
    // term, and the sentence supplies its own. The client bolds it under the same rule.
    fakeExample('I have a fever and feel very weak.');

    expect(regenerateExample('I have a fever.')->example)->toBe('I have a fever and feel very weak.');
});

it('refuses an inflected stand-in for a single word', function () {
    // The learner is taught to produce the term as written; a card that shows something else is a
    // different card. The prompt says «use the term as given», this is the guarantee.
    fakeExample('The shop only sells organics.');

    expect(fn () => regenerateExample('organic food'))->toThrow(RuntimeException::class);
});

it('loads the v2 prompt, which spells out that the example must contain the term', function () {
    fakeExample('We only buy organic food for our dog.');
    regenerateExample('organic food');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'must CONTAIN the term')
            && str_contains($system, 'verbatim')
            // Worked through on the item that failed, with the wrong answer named as wrong.
            && str_contains($system, 'How much does that coat cost?')
            // v1's content is inherited, not replaced.
            && str_contains($system, 'Use the term as given');
    });
});

it('keeps the v2 rule out of the frozen v1 prompt', function () {
    fakeExample('We only buy organic food for our dog.');
    regenerateExample('organic food', 'v1');

    Http::assertSent(function (Request $request): bool {
        return ! str_contains($request->data()['messages'][0]['content'], 'must CONTAIN the term');
    });
});

it('regenerates on v2 by default', function () {
    fakeExample('We only buy organic food for our dog.');

    (new OpenAiExampleRegenerator(app(OutboundCallContext::class), 'key', 'gpt-4o-mini'))
        ->regenerate(new ExampleRegenBrief(
            text: 'organic food',
            termLang: new LanguageCode('en'),
            translationLang: new LanguageCode('ru'),
            avoid: null,
        ));

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->data()['messages'][0]['content'], 'must CONTAIN the term');
    });
});
