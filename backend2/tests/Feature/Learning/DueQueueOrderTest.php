<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\DueTermsReader;
use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE ORDER OF THE QUEUE, and specifically the order INSIDE the block that has no order.
 *
 * `due_at ASC NULLS FIRST` is deliberate: a pair the learner is halfway up the ladder on has no due
 * date and none is wanted, and it should come before anything merely due. What that leaves is a
 * block of pairs sharing one sort key, and until now they were separated by `term_id` — a ULID,
 * which orders them by how the id happened to be minted.
 *
 * The cost was measured on a live account: two French words saved by hand in August led every
 * session the learner opened afterwards, because nothing inside the block ever moved them down.
 *
 * The fix is an ORDER, not a date: enrolment must not write `due_at` (see the invariant on
 * {@see \App\Modules\Learning\Domain\Entity\TermProgress} — «зачисление не трогает расписание», and
 * «ступень 0 ничего не планирует»), so the queue orders the block by when the row was created.
 */
function unscheduledPair(object $user, string $text, string $createdAt): string
{
    $termId = seedWordFor($user, $text, 'перевод ' . $text, enroll: false);

    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'new', 'acquisition' => 'learning', 'learning_step' => 1,
        'reps' => 0, 'successful_reviews' => 0, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 0, 'due_at' => null,
        'enrolled_at' => $createdAt, 'enrollment_sources' => json_encode(['manual']),
        'created_at' => $createdAt, 'updated_at' => $createdAt,
    ]);

    return $termId;
}

it('orders never-scheduled pairs oldest first, not by the accident of their id', function () {
    [$user] = learner();

    // Inserted newest-first on purpose: if the reader ordered by insertion or by id, this passes by
    // luck rather than by rule.
    $newest = unscheduledPair($user, 'terminal', now()->subDay()->toDateTimeString());
    $oldest = unscheduledPair($user, 'runway', now()->subMonths(3)->toDateTimeString());
    $middle = unscheduledPair($user, 'gate', now()->subMonth()->toDateTimeString());

    $ordered = array_map(
        static fn (DueTermView $v): string => $v->termId->value,
        app(DueTermsReader::class)->selectableInPool(UserId::fromString($user->id), now()->toDateTimeImmutable(), null, 50),
    );

    expect($ordered)->toBe([$oldest, $middle, $newest]);
});

it('still puts the whole unscheduled block ahead of anything merely due', function () {
    // The half of the rule that does NOT change: «unfinished before merely due» is why NULLS FIRST
    // is there, and ordering inside the block must not reach outside it.
    [$user] = learner();

    $dueYesterday = seedWordFor($user, 'boarding pass', 'посадочный талон', enroll: false);
    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $dueYesterday,
        'state' => 'review', 'acquisition' => 'graduated', 'learning_step' => 0,
        'reps' => 3, 'successful_reviews' => 3, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 5, 'due_at' => now()->subDay(),
        // Created LONG before the unscheduled pair below: age must not promote it past the block.
        'enrolled_at' => now()->subYear(), 'enrollment_sources' => json_encode(['manual']),
        'created_at' => now()->subYear(), 'updated_at' => now()->subYear(),
    ]);

    $unscheduled = unscheduledPair($user, 'aisle seat', now()->subHour()->toDateTimeString());

    $ordered = array_map(
        static fn (DueTermView $v): string => $v->termId->value,
        app(DueTermsReader::class)->selectableInPool(UserId::fromString($user->id), now()->toDateTimeImmutable(), null, 50),
    );

    expect($ordered)->toBe([$unscheduled, $dueYesterday]);
});
