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

it('finishes from active, recording usage and the result', function () {
    $dialog = newDialog();
    $dialog->finish(120, 80, '0.010000', new DateTimeImmutable('2026-08-07T10:01:00+00:00'), 'Good job.');

    expect($dialog->status())->toBe(PracticeDialogStatus::Finished)
        ->and($dialog->costUsd())->toBe('0.010000')
        ->and($dialog->tokensIn())->toBe(120)
        ->and($dialog->summary())->toBe('Good job.')
        ->and($dialog->finishedAt()?->format('c'))->toBe('2026-08-07T10:01:00+00:00');
});

it('is idempotent on a second finish — the first recorded spend and result stay', function () {
    $dialog = newDialog();
    $dialog->finish(120, 80, '0.010000', new DateTimeImmutable('2026-08-07T10:01:00+00:00'), 'First.');
    $dialog->finish(999, 999, '9.999999', new DateTimeImmutable('2026-08-07T10:02:00+00:00'), 'Second.');

    expect($dialog->status())->toBe(PracticeDialogStatus::Finished)
        ->and($dialog->costUsd())->toBe('0.010000')
        ->and($dialog->summary())->toBe('First.');
});

it('expires only from active, stamping finished_at', function () {
    $active = newDialog();
    $active->expire(0, 0, '0.000000', new DateTimeImmutable('2026-08-07T10:03:20+00:00'));
    expect($active->status())->toBe(PracticeDialogStatus::Expired)
        ->and($active->finishedAt()?->format('c'))->toBe('2026-08-07T10:03:20+00:00');

    $finished = newDialog();
    $finished->finish(1, 1, '0.000001', new DateTimeImmutable('2026-08-07T10:01:00+00:00'), null);
    expect(fn () => $finished->expire(0, 0, '0.000000', new DateTimeImmutable('2026-08-07T10:03:20+00:00')))
        ->toThrow(InvalidPracticeDialogTransition::class);
});

it('reports itself expired only when active and past the TTL', function () {
    $dialog = newDialog(expiresAt: '2026-08-07T10:03:20+00:00');

    expect($dialog->isExpiredAt(new DateTimeImmutable('2026-08-07T10:02:00+00:00')))->toBeFalse()
        ->and($dialog->isExpiredAt(new DateTimeImmutable('2026-08-07T10:04:00+00:00')))->toBeTrue();

    $dialog->finish(1, 1, '0.000001', new DateTimeImmutable('2026-08-07T10:03:20+00:00'), null);
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
