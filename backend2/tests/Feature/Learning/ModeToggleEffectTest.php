<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/** Build a study session over HTTP and return the modes it dealt. */
function dealtModes(object $ctx, string $token, bool $practice = false): array
{
    $cards = $ctx->withHeader('Authorization', "Bearer {$token}")
        // The wire names are `limit` and `practice` (BuildSessionRequest). Sending anything else
        // is silently ignored — the rules are `sometimes` — so a typo here would quietly test the
        // scheduling path while claiming to test practice.
        ->postJson('/api/v1/study/sessions', ['limit' => 10, 'practice' => $practice])
        ->assertOk()
        ->json('data.cards');

    return array_values(array_unique(array_column($cards, 'exercise_mode')));
}

it('deals only the modes the toggles leave on', function () {
    [$user, $token] = learner();
    $reservation = seedWordFor($user, 'reservation', 'бронь');
    $towel = seedWordFor($user, 'towel', 'полотенце');
    $desk = seedWordFor($user, 'front desk', 'стойка');

    // Walked off the recognition rungs, so the TOGGLE is the only filter left. A pair with no rung
    // of its own is narrowed to the easy corner of the matrix, which withholds typing — a real rule
    // (a never-met word must not be asked to be typed from memory) and not this test's subject.
    foreach ([[$reservation, 'reservation'], [$towel, 'towel'], [$desk, 'front desk']] as [$id, $text]) {
        answerTimes($this, $token, $id, $text, times: 3);
    }

    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($user->id),
        new EnabledModes([ExerciseMode::Typing]),
    );

    expect(dealtModes($this, $token, practice: true))->toBe(['typing']);
});

it('falls back to multiple_choice — loudly — when no enabled mode fits the term', function () {
    [$user, $token] = learner();
    // A single word with no example: cloze and scramble both need one. (word_bank is deliberately
    // NOT in the switched-on set below any more — since BUGFIX-2 Ч.2б D2 it assembles a single word
    // from its letters, so it fits this term and there would be nothing to fall back from.)
    seedWordFor($user, 'towel', 'полотенце');
    // A second word so the fallback card can actually be BUILT — a lone term has nothing to offer
    // beside its answer and the option floor refuses the card (QA-15). What is under test is the
    // toggle fallback, not the deck.
    seedWordFor($user, 'pillow', 'подушка');

    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($user->id),
        new EnabledModes([ExerciseMode::Cloze, ExerciseMode::Scramble]),
    );

    Log::spy();

    // The card is still playable — an empty session is a worse answer to a bad toggle.
    expect(dealtModes($this, $token, practice: true))->toBe(['multiple_choice']);

    // …and the misconfiguration left a trace, because nothing else about it is visible.
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'multiple_choice')
            && $context['term_id'] !== null)
        ->atLeast()->once();
});

it('applies a toggle to the SRS ladder too, not just to practice', function () {
    [$user, $token] = learner();
    seedWordFor($user, 'reservation', 'бронь');

    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($user->id),
        new EnabledModes([ExerciseMode::Typing]),
    );

    // A brand-new term would normally be introduced with multiple_choice; with only typing on,
    // the rung degrades inside the enabled set instead of handing out a mode that is off.
    expect(dealtModes($this, $token))->toBe(['typing']);
});

it('ships the user own mode set on every /sync page', function () {
    [$user, $token] = learner();
    app(EnabledModesWriter::class)->setOverrideFor(
        UserId::fromString($user->id),
        new EnabledModes([ExerciseMode::Typing, ExerciseMode::Scramble]),
    );
    $data = sync($this, $token);

    // The device rebuilds free practice locally, so it needs the SET, not just the cards the
    // server dealt — this is how a flipped toggle reaches the phone without a reinstall.
    expect($data['settings']['exercise_modes'])->toBe(['typing', 'scramble']);
});

it('ships the global default to a user with no override', function () {
    [, $token] = learner();

    expect(sync($this, $token)['settings']['exercise_modes'])->toBe(config('learning.enabled_modes'));
});
