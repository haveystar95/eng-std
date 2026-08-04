<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Generation\Infrastructure\Adapter\PexelsImageSearch;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function pexels(): PexelsImageSearch
{
    return new PexelsImageSearch('test-key');
}

it('maps a photo to url + attribution and sends the key in the Authorization header', function () {
    Http::fake(['*' => Http::response([
        'photos' => [[
            'photographer' => 'Jane Doe',
            'photographer_url' => 'https://www.pexels.com/@jane',
            'src' => ['landscape' => 'https://img.pexels.com/land.jpg', 'large' => 'https://img.pexels.com/large.jpg'],
        ]],
    ], 200)]);

    $result = pexels()->search('open a bank account');

    expect($result?->url)->toBe('https://img.pexels.com/land.jpg')  // landscape crop preferred
        ->and($result?->author)->toBe('Jane Doe')
        ->and($result?->authorUrl)->toBe('https://www.pexels.com/@jane');

    Http::assertSent(function (Request $request): bool {
        return $request->hasHeader('Authorization', 'test-key')
            && str_contains($request->url(), 'query=open')
            && str_contains($request->url(), 'orientation=landscape');
    });
});

it('returns null when Pexels has no match (no retry)', function () {
    Http::fake(['*' => Http::response(['photos' => []], 200)]);

    expect(pexels()->search('asdkjfhaskdjfh'))->toBeNull();
});

it('throws a transient error on 429', function () {
    Http::fake(['*' => Http::response('rate limited', 429)]);

    pexels()->search('x');
})->throws(TransientImageSearchError::class);

it('throws a transient error on a 5xx', function () {
    Http::fake(['*' => Http::response('boom', 503)]);

    pexels()->search('x');
})->throws(TransientImageSearchError::class);

it('fails loudly (non-transient) on a bad key', function () {
    Http::fake(['*' => Http::response('unauthorized', 401)]);

    pexels()->search('x');
})->throws(RuntimeException::class);
