<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A PLAN DAY IS NOT A SHELF — Д-34, Д-35.
 *
 * The day owns an ordinary collection on purpose: `type = custom`, `visibility = private`,
 * `owner_id = <the learner>`, which is exactly what lets the session machinery deal its cards
 * without knowing plans exist. Nothing said it came from a plan, so every list of «the learner's
 * folders» picked it up — «Мои коллекции» showed «Ответить на вопросы врача» beside «У врача и в
 * аптеке», and the home screen's word-challenge drew its wrong answers out of the plan's own
 * replies, while the plan ran AND long after it was archived.
 *
 * The tag is written where the day is materialised and read by the two lists that ask «what does
 * this learner keep». The delta feed still carries the row, and that is deliberate: the device
 * resolves a card's pair through its folder, so withholding it would break the plan session on the
 * phone. What changes is what a LIST does with it.
 */
beforeEach(fn () => fakePlanModel());

it('marks a plan day’s folder with its origin', function () {
    [, $token, $planId] = startedPlan($this);

    $collectionId = DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');

    expect($collectionId)->not->toBeNull()
        ->and(DB::table('collections')->where('id', $collectionId)->value('origin'))->toBe('plan')
        // …and it is an ordinary private custom folder in every other respect, because that is what
        // makes the session machinery work on it unchanged.
        ->and(DB::table('collections')->where('id', $collectionId)->value('type'))->toBe('custom')
        ->and(DB::table('collections')->where('id', $collectionId)->value('visibility'))->toBe('private');
});

it('keeps a plan day out of «Мои коллекции»', function () {
    [$user, $token, $planId] = startedPlan($this);

    // One folder the learner really does keep, so an empty list would not pass this by accident.
    $mine = app(\App\Modules\Collections\Application\Command\CreateCustomCollectionHandler::class)(
        new \App\Modules\Collections\Application\Command\CreateCustomCollection(
            \App\Modules\Shared\Domain\ValueObject\UserId::fromString($user->id),
            'У врача и в аптеке',
            new \App\Modules\Shared\Domain\ValueObject\LanguageCode('ru'),
            new \App\Modules\Shared\Domain\ValueObject\LanguageCode('en'),
        ),
    )->value;

    $listed = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/collections')
        ->assertOk()
        ->json('data');

    $ids = array_column($listed, 'id');
    $planCollections = DB::table('learning_plan_days')->where('plan_id', $planId)
        ->whereNotNull('collection_id')->pluck('collection_id')->all();

    expect($planCollections)->not->toBe([])
        ->and($ids)->toContain($mine)
        ->and(array_intersect($ids, $planCollections))->toBe([]);
});

it('still sends the plan day down the delta feed, tagged', function () {
    // The other half of the rule, and the one that keeps the phone working: a card's pair is
    // resolved through its folder, so a plan day withheld from the mirror is a plan session that
    // cannot be played.
    [, $token, $planId] = startedPlan($this);

    $planCollections = DB::table('learning_plan_days')->where('plan_id', $planId)
        ->whereNotNull('collection_id')->pluck('collection_id')->all();
    $collectionId = (string) DB::table('learning_plan_days')
        ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');

    $collections = sync($this, $token)['changes']['collections'];
    $byId = array_column($collections, null, 'id');

    expect($byId)->toHaveKey($collectionId)
        ->and($byId[$collectionId]['origin'])->toBe('plan');

    // …and the tag is not smeared over everything: only a day of a plan carries it.
    foreach ($collections as $row) {
        if (($row['op'] ?? null) === 'upsert' && ! in_array($row['id'], $planCollections, true)) {
            expect($row['origin'])->toBeNull();
        }
    }
});
