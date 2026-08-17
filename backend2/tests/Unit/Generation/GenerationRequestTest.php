<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Entity\GenerationRequest;
use App\Modules\Generation\Domain\Exception\InvalidGenerationTransition;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

function openRequest(): GenerationRequest
{
    return GenerationRequest::open(
        id: GenerationRequestId::generate(),
        userId: UserId::generate(),
        prompt: 'иду в банк',
        normalizedPrompt: 'иду в банк',
        sourceLang: new LanguageCode('ru'),
        targetLang: new LanguageCode('en'),
        levels: ['A2', 'B1'],
        size: 12,
        promptVersion: 'v1',
        createdAt: new DateTimeImmutable('2026-07-27T10:00:00Z'),
    );
}

it('opens as pending with nothing recorded', function () {
    $request = openRequest();

    expect($request->status())->toBe(GenerationStatus::Pending)
        ->and($request->collectionId())->toBeNull()
        ->and($request->finishedAt())->toBeNull();
});

it('runs then succeeds, recording collection and usage', function () {
    $request = openRequest();
    $collectionId = CollectionId::generate();

    $request->markRunning();
    $request->markSucceeded($collectionId, 'gpt-4o', 100, 200, '0.001500', 11, new DateTimeImmutable('2026-07-27T10:00:05Z'));

    expect($request->status())->toBe(GenerationStatus::Succeeded)
        ->and($request->collectionId()?->value)->toBe($collectionId->value)
        ->and($request->model())->toBe('gpt-4o')
        ->and($request->costUsd())->toBe('0.001500')
        ->and($request->deliveredCount())->toBe(11);
});

it('records usage on an attempt before the outcome is known', function () {
    $request = openRequest();
    $request->markRunning();
    $request->recordAttempt('gpt-4o', 700, 1200, '0.014500', '{"raw":true}');

    expect($request->status())->toBe(GenerationStatus::Running)
        ->and($request->model())->toBe('gpt-4o')
        ->and($request->tokensIn())->toBe(700)
        ->and($request->tokensOut())->toBe(1200)
        ->and($request->rawResponse())->toBe('{"raw":true}');
});

it('keeps recorded usage after a validation failure', function () {
    $request = openRequest();
    $request->markRunning();
    $request->recordAttempt('gpt-4o', 700, 1200, '0.014500', '{"raw":true}');
    $request->markFailed('only 3 usable items after validation', new DateTimeImmutable('2026-07-27T10:00:05Z'));

    expect($request->status())->toBe(GenerationStatus::Failed)
        ->and($request->error())->toBe('only 3 usable items after validation')
        ->and($request->tokensIn())->toBe(700)
        ->and($request->costUsd())->toBe('0.014500')
        ->and($request->rawResponse())->toBe('{"raw":true}');
});

it('can fail straight from pending', function () {
    $request = openRequest();

    $request->markFailed('quota', new DateTimeImmutable('2026-07-27T10:00:01Z'));

    expect($request->status())->toBe(GenerationStatus::Failed)
        ->and($request->error())->toBe('quota');
});

it('cannot run a request twice', function () {
    $request = openRequest();
    $request->markRunning();

    expect(fn () => $request->markRunning())->toThrow(InvalidGenerationTransition::class);
});

it('cannot succeed after failing', function () {
    $request = openRequest();
    $request->markFailed('boom', new DateTimeImmutable('2026-07-27T10:00:01Z'));

    expect(fn () => $request->markSucceeded(
        CollectionId::generate(), 'gpt-4o', null, null, null, 8, new DateTimeImmutable('2026-07-27T10:00:02Z'),
    ))->toThrow(InvalidGenerationTransition::class);
});
