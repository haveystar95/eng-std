<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Domain\Service\NegativeVerdictLifetime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * «Это не слово» GOES OFF (наряд A-4.1 Ч.2 п.2, решение владельца 24.08).
 *
 * The lookup cache is global and was permanent in both directions, so one bad second from the model
 * closed a word for every learner in the deployment forever. It happened: «привет» asked in the
 * pair `es ← ru` came back `not_recognized`, while «как дела ?» in the same pair the same evening
 * answered `cómo estás`. Re-asking was free and re-served the same «no», so nothing could ever
 * correct it — the mistake was cheaper to keep than to fix.
 *
 * A refusal now lives 24 hours. Inside the day it still does its job (a paste-and-retry loop buys
 * nothing); after it, the word is simply asked again.
 *
 * There is still no «повторить» BUTTON — but there did not need to be one, and that is the second
 * half of this file (решение архитектора 25.08). The learner already has a button: «Собрать
 * карточку». Pressing it a second time on a query that was just refused IS the retry, so an explicit
 * re-tap goes past the stored verdict outright, fresh or not. The expiry then governs exactly what
 * it should: the automatic paths, where nobody is watching and nobody asked.
 */
final class SpyWordLookup implements WordLookupPort
{
    public static int $calls = 0;

    /** When true the model «changes its mind» and places the word it refused before. */
    public static bool $recognizes = false;

    public static function reset(): void
    {
        self::$calls = 0;
        self::$recognizes = false;
    }

    public function lookUp(WordLookupBrief $brief): WordLookupResult
    {
        self::$calls++;

        if (self::$recognizes) {
            return new WordLookupResult(
                text: 'greeting',
                type: 'word',
                translation: 'приветствие',
                // Never contains the term: the barrier refuses a description that gives its own
                // word away, and a fake that trips it would make this file assert the wrong path.
                description: 'A polite word you say when you meet somebody.',
                example: 'She gave me a warm greeting at the door.',
                exampleTranslation: 'Она тепло поздоровалась со мной у двери.',
                cefr: 'A2',
                transcription: null,
                imageApiPrompt: 'people greeting each other',
                model: 'spy',
                promptVersion: 'lookup.spy',
                tokensIn: 10,
                tokensOut: 10,
                costUsd: '0.000010',
            );
        }

        return new WordLookupResult(
            text: '', type: 'word', translation: '', description: '',
            example: null, exampleTranslation: null, cefr: null, transcription: null,
            imageApiPrompt: null, model: 'spy', promptVersion: 'lookup.spy',
            tokensIn: 10, tokensOut: 5, costUsd: '0.000010',
            notRecognized: true,
        );
    }
}

beforeEach(function (): void {
    SpyWordLookup::reset();
    $this->app->bind(WordLookupPort::class, SpyWordLookup::class);
});

/** POST /search/lookup, unwrapped. Always a 200 — a refusal is an answer here, not an error. */
function askLookup(object $ctx, string $token, string $query, bool $retry = false): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/search/lookup', ['query' => $query] + ($retry ? ['retry' => true] : []))
        ->assertOk()
        ->json('data');
}

/** Move the one cached refusal back in time, which is what a day passing looks like to the reader. */
function ageTheRefusal(int $hours): void
{
    DB::table('search_lookups')->update(['created_at' => now()->subHours($hours)]);
}

describe('the rule itself', function () {
    it('keeps a refusal written a moment ago', function () {
        $now = new DateTimeImmutable('2026-08-24 20:00:00');

        expect(NegativeVerdictLifetime::isStale($now->modify('-1 hour'), $now))->toBeFalse();
    });

    it('drops one that has come of age exactly', function () {
        $now = new DateTimeImmutable('2026-08-24 20:00:00');

        expect(NegativeVerdictLifetime::isStale($now->modify('-24 hours'), $now))->toBeTrue();
    });

    it('drops an older one', function () {
        $now = new DateTimeImmutable('2026-08-24 20:00:00');

        expect(NegativeVerdictLifetime::isStale($now->modify('-8 days'), $now))->toBeTrue();
    });
});

it('serves a fresh refusal from the cache and buys nothing', function () {
    [, $token] = learner();

    expect(askLookup($this, $token, 'asdfgh')['not_recognized'])->toBeTrue();
    expect(SpyWordLookup::$calls)->toBe(1);

    // Second ask, same minute: the row answers, the model is not touched. This is the half of the
    // old behaviour that was right and has to survive the expiry.
    expect(askLookup($this, $token, 'asdfgh')['not_recognized'])->toBeTrue();
    expect(SpyWordLookup::$calls)->toBe(1);
    expect(DB::table('search_lookups')->count())->toBe(1);
});

it('asks again once the refusal is a day old', function () {
    [, $token] = learner();

    askLookup($this, $token, 'asdfgh');
    ageTheRefusal(25);

    expect(askLookup($this, $token, 'asdfgh')['not_recognized'])->toBeTrue();
    expect(SpyWordLookup::$calls)->toBe(2, 'a stale «no» is a miss, not an answer');
    // Still ONE row: the re-ask replaces the verdict rather than piling a second one beside it.
    expect(DB::table('search_lookups')->count())->toBe(1);
});

it('lets the word come back — the whole point of the expiry', function () {
    [, $token] = learner();

    askLookup($this, $token, 'asdfgh');
    ageTheRefusal(25);
    SpyWordLookup::$recognizes = true;

    $answer = askLookup($this, $token, 'asdfgh');

    expect($answer['not_recognized'])->toBeFalse()
        ->and($answer['lookup']['text'])->toBe('greeting')
        ->and($answer['lookup']['translation'])->toBe('приветствие');
});

it('restarts the clock on the refreshed row, so the second chance is not every request', function () {
    [, $token] = learner();

    askLookup($this, $token, 'asdfgh');
    ageTheRefusal(25);
    askLookup($this, $token, 'asdfgh');

    // A row rewritten today, not one still dated last week: without this the refusal would be stale
    // forever and every single lookup of it would buy a call.
    $writtenAt = DB::table('search_lookups')->value('created_at');
    expect(now()->diffInHours($writtenAt, absolute: true))->toBeLessThan(1);

    askLookup($this, $token, 'asdfgh');
    expect(SpyWordLookup::$calls)->toBe(2, 'the refreshed refusal is fresh again and answers free');
});

describe('a re-tap outranks the verdict (решение архитектора 25.08)', function () {
    // The expiry is for the paths nobody watches. A person looking at «не получилось распознать»
    // over a word they know exists, pressing the button a second time, IS the retry — and making
    // them wait a day is the app defending a mistake it can see on screen.

    it('ignores a refusal written seconds ago', function () {
        [, $token] = learner();

        askLookup($this, $token, 'asdfgh');
        expect(SpyWordLookup::$calls)->toBe(1);

        // No ageing anywhere: the row is as fresh as it gets, and the re-tap goes past it.
        SpyWordLookup::$recognizes = true;
        $answer = askLookup($this, $token, 'asdfgh', retry: true);

        expect(SpyWordLookup::$calls)->toBe(2)
            ->and($answer['not_recognized'])->toBeFalse()
            ->and($answer['lookup']['text'])->toBe('greeting');
    });

    it('does not re-buy a card that already exists', function () {
        [, $token] = learner();

        SpyWordLookup::$recognizes = true;
        askLookup($this, $token, 'asdfgh');
        expect(SpyWordLookup::$calls)->toBe(1);

        // There is nothing to dispute about a positive answer, so the flag changes nothing: the
        // card is served from the cache and no money is spent.
        $again = askLookup($this, $token, 'asdfgh', retry: true);

        expect(SpyWordLookup::$calls)->toBe(1)
            ->and($again['lookup']['text'])->toBe('greeting');
    });

    it('does not bypass the daily cap — a retry loop is what the cap is for', function () {
        [, $token] = learner();
        // A cap of zero is the cheapest way to say «spent», and the retry must not walk past it.
        config(['services.generation.search_lookup_daily_cap' => 0]);

        $answer = askLookup($this, $token, 'asdfgh', retry: true);

        expect($answer['limit_reached'])->toBeTrue()
            ->and(SpyWordLookup::$calls)->toBe(0);
    });

    it('leaves the automatic path governed by the expiry alone', function () {
        [, $token] = learner();

        askLookup($this, $token, 'asdfgh');
        // Same query, no flag, no ageing: the refusal answers and nothing is bought.
        askLookup($this, $token, 'asdfgh');

        expect(SpyWordLookup::$calls)->toBe(1);
    });
});
