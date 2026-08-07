<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Generation\Domain\Exception\InvalidPracticeDialogTransition;
use App\Modules\Generation\Domain\ValueObject\PracticeDialogStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;

function newDialog(string $createdAt = '2026-08-07T10:00:00+00:00', string $expiresAt = '2026-08-07T10:03:20+00:00'): PracticeDialog
{
    return PracticeDialog::open(
        id: PracticeDialogId::generate(),
        userId: UserId::generate(),
        collectionId: CollectionId::generate(),
        lesson: ['topic' => 'At the bank', 'model' => 'gpt-realtime-mini'],
        expiresAt: new DateTimeImmutable($expiresAt),
        createdAt: new DateTimeImmutable($createdAt),
    );
}

it('starts active with no recorded spend', function () {
    $dialog = newDialog();

    expect($dialog->status())->toBe(PracticeDialogStatus::Active)
        ->and($dialog->costUsd())->toBeNull();
});

it('finishes from active, recording usage', function () {
    $dialog = newDialog();
    $dialog->finish(120, 80, '0.010000');

    expect($dialog->status())->toBe(PracticeDialogStatus::Finished)
        ->and($dialog->costUsd())->toBe('0.010000')
        ->and($dialog->tokensIn())->toBe(120);
});

it('is idempotent on a second finish — the first recorded spend stays', function () {
    $dialog = newDialog();
    $dialog->finish(120, 80, '0.010000');
    $dialog->finish(999, 999, '9.999999');

    expect($dialog->status())->toBe(PracticeDialogStatus::Finished)
        ->and($dialog->costUsd())->toBe('0.010000');
});

it('expires only from active', function () {
    $dialog = newDialog();
    $dialog->finish(1, 1, '0.000001');

    expect(fn () => $dialog->expire(0, 0, '0.000000'))
        ->toThrow(InvalidPracticeDialogTransition::class);
});

it('reports itself expired only when active and past the TTL', function () {
    $dialog = newDialog(expiresAt: '2026-08-07T10:03:20+00:00');

    expect($dialog->isExpiredAt(new DateTimeImmutable('2026-08-07T10:02:00+00:00')))->toBeFalse()
        ->and($dialog->isExpiredAt(new DateTimeImmutable('2026-08-07T10:04:00+00:00')))->toBeTrue();

    $dialog->finish(1, 1, '0.000001');
    // Once finished it is never "expired" (that transition is closed).
    expect($dialog->isExpiredAt(new DateTimeImmutable('2026-08-07T11:00:00+00:00')))->toBeFalse();
});

it('bills the elapsed seconds, clamped to the token expiry', function () {
    $dialog = newDialog(createdAt: '2026-08-07T10:00:00+00:00', expiresAt: '2026-08-07T10:03:20+00:00');

    // Mid-session.
    expect($dialog->billableSeconds(new DateTimeImmutable('2026-08-07T10:01:00+00:00')))->toBe(60);
    // Past expiry → clamped to the full 200s TTL.
    expect($dialog->billableSeconds(new DateTimeImmutable('2026-08-07T11:00:00+00:00')))->toBe(200);
});
