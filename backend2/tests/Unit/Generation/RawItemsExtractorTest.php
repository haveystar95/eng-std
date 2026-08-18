<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\RawItemsExtractor;

it('parses a clean, unclosed-only-by-trailing-garbage response as not truncated', function () {
    $content = '{"title":"T","description":"d","items":[{"text":"a","type":"word"},{"text":"b","type":"word"}],"collection_image';

    $result = RawItemsExtractor::extract($content);

    expect($result['truncated'])->toBeFalse()
        ->and($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['text'])->toBe('a')
        ->and($result['items'][1]['text'])->toBe('b');
});

it('salvages every complete item and drops the dangling partial one when truncated mid-item', function () {
    $content = '{"title":"T","description":"d","items":[{"text":"a","type":"word"},{"text":"b","transcription":"br';

    $result = RawItemsExtractor::extract($content);

    expect($result['truncated'])->toBeTrue()
        ->and($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['text'])->toBe('a');
});

it('is tolerant of quoted brackets and escaped quotes inside item strings', function () {
    $content = '{"items":[{"text":"say \\"hi [there]\\"","note":"a [bracket] and \\"quote\\""},{"text":"clean"}]}';

    $result = RawItemsExtractor::extract($content);

    expect($result['truncated'])->toBeFalse()
        ->and($result['items'])->toHaveCount(2)
        ->and($result['items'][0]['text'])->toBe('say "hi [there]"')
        ->and($result['items'][1]['text'])->toBe('clean');
});

it('throws when there is no items key at all', function () {
    expect(fn () => RawItemsExtractor::extract('{"title":"T"}'))->toThrow(RuntimeException::class);
});

it('throws when truncation cuts off before even one complete item', function () {
    expect(fn () => RawItemsExtractor::extract('{"items":[{"text":"a'))->toThrow(RuntimeException::class);
});

it('unwraps an OpenAI chat-completion envelope before extracting', function () {
    $body = [
        'choices' => [
            ['message' => ['content' => '{"items":[{"text":"pharmacy","type":"word"}]}']],
        ],
    ];

    $result = RawItemsExtractor::extractFromLoggedResponse($body);

    expect($result['items'])->toHaveCount(1)
        ->and($result['items'][0]['text'])->toBe('pharmacy');
});

it('throws when the logged response body was redacted/truncated at log time', function () {
    // EloquentApiLogWriter::prepareBody() replaces an over-size body with this shape.
    $body = ['_truncated' => true, 'bytes' => 16456];

    expect(fn () => RawItemsExtractor::extractFromLoggedResponse($body))->toThrow(RuntimeException::class);
});
