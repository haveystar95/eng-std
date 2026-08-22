<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->app->bind(WordLookupPort::class, FakeWordLookup::class);
});

/** POST /search/lookup, unwrapped. Always a 200 — every «no» here is an answer, not an error. */
function lookup(object $ctx, string $token, string $query): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => $query])
        ->assertOk()
        ->json('data');
}

it('builds an ENGLISH card from a Russian query', function () {
    [, $token] = learner();

    $card = lookup($this, $token, 'Случай')['lookup'];

    // The term is always the word being LEARNED. A Russian query that came back as a Russian term
    // would be a translator's answer, and this app is not a translator.
    expect($card['text'])->toBe('occasion')
        ->and($card['translation'])->toBe('случай')
        ->and($card['fresh'])->toBeTrue();
});

it('builds a card from a Russian PHRASE, which is a term like any other', function () {
    [, $token] = learner();

    $card = lookup($this, $token, 'как дела')['lookup'];

    expect($card['text'])->toBe('how are you')->and($card['type'])->toBe('phrase');
});

it('lands both spellings of the same word on ONE term', function () {
    [, $token] = learner();

    // Two different queries, two cache rows — the cache key is the query, and «случай» and
    // «occasion» really are two different questions somebody asked.
    $viaRussian = lookup($this, $token, 'случай')['lookup']['lookup_id'];
    $viaEnglish = lookup($this, $token, 'occasion')['lookup']['lookup_id'];
    expect($viaEnglish)->not->toBe($viaRussian);
    expect(DB::table('search_lookups')->count())->toBe(2);

    $first = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $viaRussian])->assertCreated()->json('data.term_id');
    $second = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $viaEnglish])->assertCreated()->json('data.term_id');

    // …but the WORD is one word. Dedup is on the English term, which is the only thing either
    // question was ever about.
    expect($second)->toBe($first);
    expect(DB::table('terms')->where('text', 'occasion')->count())->toBe(1);
});

it('serves a Russian query from the cache the second time, free', function () {
    [, $token] = learner();
    [, $otherToken] = learner();

    lookup($this, $token, 'случай');
    $again = lookup($this, $otherToken, '  СЛУЧАЙ ')['lookup'];

    expect($again['text'])->toBe('occasion')->and($again['fresh'])->toBeFalse();
    expect(DB::table('search_lookups')->count())->toBe(1);
});

it('says plainly when it cannot place the query, without pretending to a card', function () {
    [, $token] = learner();

    $answer = lookup($this, $token, 'asdfgh');

    expect($answer['not_recognized'])->toBeTrue()
        ->and($answer['lookup'])->toBeNull()
        // Not the cap and not an error: the client's line is «проверьте написание», which is advice.
        ->and($answer['limit_reached'])->toBeFalse();
});

it('remembers that a query was not a word, so the second paste costs nothing', function () {
    [, $token] = learner();

    lookup($this, $token, 'asdfgh');
    lookup($this, $token, 'asdfgh');

    // ONE row, and it exists on purpose: the daily cap counts rows, so a refusal that wrote none
    // would be a paid call nobody was charged for.
    expect(DB::table('search_lookups')->count())->toBe(1);
    expect(DB::table('search_lookups')->value('payload'))->toContain('not_recognized');
    // …and no term was invented for it.
    expect(DB::table('terms')->count())->toBe(0);
});

it('counts a refusal against the day, exactly like any other bought call', function () {
    $this->app->bind(
        \App\Modules\Generation\Domain\Service\SearchLookupDailyLimit::class,
        fn () => new \App\Modules\Generation\Domain\Service\SearchLookupDailyLimit(1),
    );
    [, $token] = learner();

    lookup($this, $token, 'asdfgh');

    expect(lookup($this, $token, 'reimbursement')['limit_reached'])->toBeTrue();
});

it('saves a Russian-asked word into «Сохранённые» as an English term', function () {
    [$user, $token] = learner();
    $lookupId = lookup($this, $token, 'случай')['lookup']['lookup_id'];

    $saved = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])
        ->assertCreated()
        ->assertJsonPath('data.collection_is_default', true)
        ->json('data');

    // English term, Russian translation, in the pool — the same shape a word asked for in English
    // gets. The language the question was asked in leaves no trace on the card.
    $this->assertDatabaseHas('terms', ['id' => $saved['term_id'], 'text' => 'occasion', 'lang' => 'en']);
    $this->assertDatabaseHas('term_translations', [
        'term_id' => $saved['term_id'], 'lang' => 'ru', 'text' => 'случай',
    ]);
    expect(DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $saved['term_id'])
        ->whereNotNull('enrolled_at')->count())->toBe(1);
});

it('finds the saved word on the next free search, from either side', function () {
    [, $token] = learner();
    $lookupId = lookup($this, $token, 'случай')['lookup']['lookup_id'];
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/add', ['lookup_id' => $lookupId])->assertCreated();

    foreach (['случай', 'occasion'] as $query) {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/search?q=' . urlencode($query))
            ->assertOk()
            ->assertJsonPath('data.0.text', 'occasion');
    }
});
