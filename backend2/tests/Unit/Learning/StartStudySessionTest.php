<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Command\StartStudySession;
use App\Modules\Learning\Application\Command\StartStudySessionHandler;
use App\Modules\Learning\Domain\ValueObject\StudyMode;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryStudySessionRepository;

beforeEach(function () {
    $this->sessions = new InMemoryStudySessionRepository();
    $this->clock = new FixedClock(new DateTimeImmutable('2026-07-27T08:00:00Z'));
    $this->handler = new StartStudySessionHandler($this->sessions, $this->clock);
});

it('opens a session and returns its id', function () {
    $id = ($this->handler)(new StartStudySession(UserId::generate(), StudyMode::Flashcard));

    expect($this->sessions->count())->toBe(1)
        ->and($id)->toBeInstanceOf(StudySessionId::class);
});

it('is idempotent on a client-supplied session id', function () {
    $clientId = StudySessionId::generate();
    $command = new StartStudySession(UserId::generate(), StudyMode::Typing, sessionId: $clientId);

    ($this->handler)($command);
    $second = ($this->handler)($command);

    expect($second->equals($clientId))->toBeTrue()
        ->and($this->sessions->count())->toBe(1);
});
