<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function fakeOpenAi(): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['title' => 't', 'description' => 'd', 'items' => []])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);
}

function generateWith(string $version): void
{
    (new OpenAiCollectionGenerator('key', 'gpt-4o', $version))->generate(
        new GenerationBrief('в банке', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 15),
    );
}

it('loads the v3 prompt with the four-type taxonomy and a first-class AVOID block', function () {
    fakeOpenAi();
    generateWith('v3');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'phrasal_verb') && str_contains($system, 'ALREADY SELECTED');
    });
});

it('keeps the frozen v2 prompt free of the v3 additions', function () {
    fakeOpenAi();
    generateWith('v2');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'phrasal_verb') && ! str_contains($system, 'ALREADY SELECTED');
    });
});
