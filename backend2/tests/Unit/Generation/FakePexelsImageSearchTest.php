<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\TransientImageSearchError;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;

it('returns a stable result in found mode', function () {
    $fake = new FakePexelsImageSearch(FakePexelsImageSearch::FOUND);

    $a = $fake->search('open a bank account');
    $b = $fake->search('open a bank account');

    expect($a)->not->toBeNull()
        ->and($a?->url)->toBe($b?->url)          // deterministic
        ->and($a?->author)->toBe('Fake Photographer')
        ->and($a?->authorUrl)->toBe('https://pexels.test/@fake')
        ->and($fake->calls)->toBe(2);
});

it('returns null for an empty query even in found mode', function () {
    expect((new FakePexelsImageSearch())->search('   '))->toBeNull();
});

it('returns null in not_found mode (no retry)', function () {
    expect((new FakePexelsImageSearch(FakePexelsImageSearch::NOT_FOUND))->search('anything'))->toBeNull();
});

it('throws a transient error in rate_limited mode', function () {
    (new FakePexelsImageSearch(FakePexelsImageSearch::RATE_LIMITED))->search('x');
})->throws(TransientImageSearchError::class);

it('throws a transient error in transient_error mode', function () {
    (new FakePexelsImageSearch(FakePexelsImageSearch::TRANSIENT_ERROR))->search('x');
})->throws(TransientImageSearchError::class);
