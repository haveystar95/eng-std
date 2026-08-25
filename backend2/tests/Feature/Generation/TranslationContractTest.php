<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SYN-1 Ч.2 п. 2 — the translation contract.
 *
 * The learner reads a line in the translator, agrees with it by pressing «Собрать карточку», and
 * from then on that is what the card asks. Nothing downstream re-decides it: not the lookup model,
 * not a second lookup of the same word, not a dedup merge from a generated collection.
 */
beforeEach(function (): void {
    $this->app->bind(WordLookupPort::class, FakeWordLookup::class);
});

/** The whole Build path: look the word up, then save it, both carrying the confirmed line. */
function buildAndSave(object $ctx, string $token, string $query, ?string $confirmed): string
{
    $lookup = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', array_filter([
            'query' => $query,
            'fixed_translation' => $confirmed,
        ], static fn (mixed $v): bool => $v !== null))
        ->assertOk()
        ->json('data.lookup');

    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', array_filter([
            'lookup_id' => $lookup['lookup_id'],
            'fixed_translation' => $confirmed,
        ], static fn (mixed $v): bool => $v !== null))
        ->assertCreated()
        ->json('data.term_id');
}

it('pins the translation the learner confirmed, not the one the model preferred', function () {
    [, $token] = learner();

    $termId = buildAndSave($this, $token, 'случай', 'событие');

    $primary = DB::table('term_translations')
        ->where('term_id', $termId)->where('is_primary', true)->value('text');

    expect($primary)->toBe('событие');
});

it('keeps the model\'s own reading beside it rather than throwing it away', function () {
    [, $token] = learner();

    $termId = buildAndSave($this, $token, 'случай', 'событие');

    $all = DB::table('term_translations')->where('term_id', $termId)->pluck('text')->all();

    // The fake answers «случай» and offers «другой перевод» as another reading; both survive as
    // alternatives, and neither competes to be the question.
    expect($all)->toContain('событие')->toContain('случай')->toContain('другой перевод');
});

it('leaves the model in charge when nothing was confirmed', function () {
    [, $token] = learner();

    $termId = buildAndSave($this, $token, 'случай', null);

    expect(DB::table('term_translations')->where('term_id', $termId)->where('is_primary', true)->value('text'))
        ->toBe('случай');
});

it('a second save of the same word does not re-word the card', function () {
    [$user, $token] = learner();
    $termId = buildAndSave($this, $token, 'случай', 'событие');

    // Somebody else saves the same word — a cache HIT, so no call is bought and the model has no
    // say — with no confirmation of their own.
    [, $other] = learner();
    $again = buildAndSave($this, $other, 'случай', null);

    expect($again)->toBe($termId)
        ->and(DB::table('term_translations')->where('term_id', $termId)->where('is_primary', true)->value('text'))
        ->toBe('событие')
        // …and still exactly one primary. Both halves of the rule, on the live path.
        ->and(DB::table('term_translations')->where('term_id', $termId)->where('is_primary', true)->count())
        ->toBe(1);

    expect($user)->not->toBeNull();
});

it('writes NO synonyms while the product is switched off — which is the default', function () {
    [, $token] = learner();

    // DECISIONS п. 32: a new product ships switched off globally. Pinned as a test because the
    // default is the whole safety property — a live `POST /search/add` wrote `cont` → `factură`
    // before this switch existed (docs/syn-1-findings.md §7).
    $termId = buildAndSave($this, $token, 'случай', null);

    expect(DB::table('term_synonyms')->where('term_id', $termId)->count())->toBe(0);
});

it('stores the synonyms the lookup came back with once it is switched on', function () {
    config(['services.generation.write_synonyms' => true]);
    [, $token] = learner();

    $termId = buildAndSave($this, $token, 'случай', null);

    expect(DB::table('term_synonyms')->where('term_id', $termId)->orderBy('text')->pluck('text')->all())
        ->toBe(['instance', 'sample'])
        // The term's own language, read off the term and never from the caller.
        ->and(DB::table('term_synonyms')->where('term_id', $termId)->value('lang'))->toBe('en');
});


it('holds a lookup\'s synonyms to the same shape rules the станок obeys', function () {
    config(['services.generation.write_synonyms' => true]);
    [, $token] = learner();

    // «как дела» is a PHRASE. The fake proposes two synonyms for every word it answers; the shape
    // rules refuse them here for the same reason they refuse them on the станок path — what a
    // "synonym" of a phrase would be is a paraphrase, and a paraphrase accepted as an answer widens
    // the key to a different utterance. One table, one set of rules, whichever writer arrives.
    $termId = buildAndSave($this, $token, 'как дела', null);

    expect(DB::table('term_synonyms')->where('term_id', $termId)->count())->toBe(0);
});
